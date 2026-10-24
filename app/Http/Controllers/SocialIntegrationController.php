<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache, Crypt, DB, Http, Log};
use Illuminate\Support\Str;
use Throwable;

class SocialIntegrationController extends Controller
{
    public function start(Request $request, string $provider)
    {
        abort_unless(in_array($provider, ['meta', 'tiktok'], true), 404);

        $request->validate([
            'store_id' => ['nullable', 'integer'],
        ]);

        $sellerId = DB::table('sellers')
            ->where('user_id', auth()->id())
            ->value('id');

        abort_unless($sellerId, 403, 'Seller profile not found.');

        $storeId = $request->integer('store_id') ?: null;

        if ($storeId) {
            $ownsStore = DB::table('seller_store_settings')
                ->where('id', $storeId)
                ->where('seller_id', $sellerId)
                ->exists();

            abort_unless($ownsStore, 403, 'Store does not belong to you.');
        }

        $state = Str::random(64);

        Cache::put('social_oauth:' . $state, [
            'seller_id' => (int) $sellerId,
            'store_id' => $storeId,
            'provider' => $provider,
        ], now()->addMinutes(10));

        if ($provider === 'meta') {
            $config = config('services.meta');

            abort_unless(
                $config['app_id'] && $config['app_secret']
                && $config['redirect_uri']
                && !str_contains($config['graph_version'], 'XX'),
                503,
                'Meta OAuth configuration is incomplete.'
            );

            $url = 'https://www.facebook.com/'
                . $config['graph_version'] . '/dialog/oauth';

            return redirect()->away($url . '?' . http_build_query([
                'client_id' => $config['app_id'],
                'redirect_uri' => $config['redirect_uri'],
                'state' => $state,
                'response_type' => 'code',
                'scope' => implode(',', [
                    'pages_show_list',
                    'pages_read_engagement',
                    'instagram_basic',
                    'instagram_content_publish',
                ]),
            ]));
        }

        $config = config('services.tiktok');

        abort_unless(
            $config['client_key'] && $config['client_secret']
            && $config['redirect_uri'],
            503,
            'TikTok OAuth configuration is incomplete.'
        );

        return redirect()->away(
            'https://www.tiktok.com/v2/auth/authorize/?'
            . http_build_query([
                'client_key' => $config['client_key'],
                'redirect_uri' => $config['redirect_uri'],
                'response_type' => 'code',
                'scope' => 'user.info.basic,video.list',
                'state' => $state,
            ])
        );
    }

    public function callback(Request $request, string $provider)
    {
        abort_unless(in_array($provider, ['meta', 'tiktok'], true), 404);

        $frontend = rtrim(
            config('services.social.frontend_url'),
            '/'
        );

        if ($request->filled('error')) {
            return redirect()->away(
                $frontend . '/seller/social-integration?status=denied'
            );
        }

        $request->validate([
            'state' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);

        $key = 'social_oauth:' . $request->input('state');
        $context = Cache::pull($key);

        abort_unless(
            $context
            && $context['provider'] === $provider,
            403,
            'Invalid or expired OAuth state.'
        );

        try {
            if ($provider === 'meta') {
                $this->completeMeta($request->string('code')->toString(), $context);
            } else {
                $this->completeTikTok($request->string('code')->toString(), $context);
            }

            return redirect()->away(
                $frontend . '/seller/social-integration?status=connected'
            );
        } catch (Throwable $e) {
            Log::error('Social OAuth callback failed', [
                'provider' => $provider,
                'seller_id' => $context['seller_id'],
                'error' => $e->getMessage(),
            ]);

            return redirect()->away(
                $frontend . '/seller/social-integration?status=failed'
            );
        }
    }

    public function accounts()
    {
        $sellerId = DB::table('sellers')
            ->where('user_id', auth()->id())
            ->value('id');

        abort_unless($sellerId, 403, 'Seller profile not found.');

        $accounts = DB::table('social_accounts')
            ->where('seller_id', $sellerId)
            ->select(
                'id',
                'store_id',
                'provider',
                'provider_account_id',
                'username',
                'account_name',
                'status',
                'scopes',
                'connected_at',
                'last_synced_at',
                'token_expires_at'
            )
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $accounts,
        ]);
    }

    public function disconnect(int $id)
    {
        $sellerId = DB::table('sellers')
            ->where('user_id', auth()->id())
            ->value('id');

        abort_unless($sellerId, 403, 'Seller profile not found.');

        $account = DB::table('social_accounts')
            ->where('id', $id)
            ->where('seller_id', $sellerId)
            ->first();

        abort_unless($account, 404, 'Social account not found.');

        DB::table('social_accounts')
            ->where('id', $id)
            ->where('seller_id', $sellerId)
            ->update([
                'status' => 'disconnected',
                'access_token' => null,
                'refresh_token' => null,
                'token_expires_at' => null,
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Social account disconnected.',
        ]);
    }

    public function posts(Request $request)
    {
        $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'social_account_id' => ['sometimes', 'integer'],
        ]);

        $sellerId = DB::table('sellers')
            ->where('user_id', auth()->id())
            ->value('id');

        abort_unless($sellerId, 403, 'Seller profile not found.');

        $query = DB::table('social_posts as p')
            ->join('social_accounts as a', 'a.id', '=', 'p.social_account_id')
            ->leftJoin('products as pr', 'pr.id', '=', 'p.product_id')
            ->where('a.seller_id', $sellerId)
            ->select(
                'p.id',
                'p.social_account_id',
                'a.provider',
                'p.provider_post_id',
                'p.product_id',
                'pr.name as product_name',
                'p.post_type',
                'p.caption',
                'p.media_url',
                'p.permalink',
                'p.published_at',
                'p.sync_status'
            );

        if ($request->filled('social_account_id')) {
            $query->where(
                'p.social_account_id',
                $request->integer('social_account_id')
            );
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderByDesc('p.id')
                ->paginate($request->integer('per_page', 15)),
        ]);
    }

    public function mapProduct(Request $request, int $postId)
    {
        $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ]);

        $sellerId = DB::table('sellers')
            ->where('user_id', auth()->id())
            ->value('id');

        abort_unless($sellerId, 403, 'Seller profile not found.');

        $post = DB::table('social_posts as p')
            ->join('social_accounts as a', 'a.id', '=', 'p.social_account_id')
            ->where('p.id', $postId)
            ->where('a.seller_id', $sellerId)
            ->select('p.id')
            ->first();

        abort_unless($post, 404, 'Social post not found.');

        $ownsProduct = DB::table('products as p')
            ->join('seller_store_settings as s', 's.id', '=', 'p.store_id')
            ->where('p.id', $request->integer('product_id'))
            ->where('s.seller_id', $sellerId)
            ->exists();

        abort_unless($ownsProduct, 403, 'Product does not belong to you.');

        DB::table('social_posts')
            ->where('id', $postId)
            ->update([
                'product_id' => $request->integer('product_id'),
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Product mapped to social post.',
        ]);
    }

    public function syncHistory(Request $request)
    {
        $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $sellerId = DB::table('sellers')
            ->where('user_id', auth()->id())
            ->value('id');

        abort_unless($sellerId, 403, 'Seller profile not found.');

        $logs = DB::table('social_sync_logs')
            ->where('seller_id', $sellerId)
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    private function saveAccount(
        array $context,
        string $provider,
        string $providerId,
        string $name,
        ?string $username,
        string $accessToken,
        ?string $refreshToken,
        ?int $expiresIn,
        array $scopes
    ): void {
        $now = now();

        DB::table('social_accounts')->updateOrInsert(
            [
                'seller_id' => $context['seller_id'],
                'provider' => $provider,
                'provider_account_id' => $providerId,
            ],
            [
                'store_id' => $context['store_id'],
                'account_name' => $name,
                'username' => $username,
                'access_token' => Crypt::encryptString($accessToken),
                'refresh_token' => $refreshToken
                    ? Crypt::encryptString($refreshToken)
                    : null,
                'token_expires_at' => $expiresIn
                    ? $now->copy()->addSeconds($expiresIn)
                    : null,
                'scopes' => json_encode($scopes),
                'status' => 'connected',
                'connected_at' => $now,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    private function completeMeta(string $code, array $context): void
    {
        $config = config('services.meta');
        $base = 'https://graph.facebook.com/'
            . $config['graph_version'];

        $tokenResponse = Http::get($base . '/oauth/access_token', [
            'client_id' => $config['app_id'],
            'client_secret' => $config['app_secret'],
            'redirect_uri' => $config['redirect_uri'],
            'code' => $code,
        ])->throw()->json();

        $userToken = $tokenResponse['access_token'] ?? null;

        if (!$userToken) {
            throw new \RuntimeException('Meta did not return an access token.');
        }

        $pages = Http::withToken($userToken)
            ->get($base . '/me/accounts', [
                'fields' => 'id,name,access_token,instagram_business_account{id,username}',
            ])
            ->throw()
            ->json('data', []);

        foreach ($pages as $page) {
            if (empty($page['id']) || empty($page['access_token'])) {
                continue;
            }

            $pageToken = $page['access_token'];

            $this->saveAccount(
                $context,
                'facebook',
                (string) $page['id'],
                $page['name'] ?? 'Facebook Page',
                null,
                $pageToken,
                null,
                null,
                ['pages_show_list', 'pages_read_engagement']
            );

            $instagram = $page['instagram_business_account'] ?? null;

            if ($instagram && !empty($instagram['id'])) {
                $this->saveAccount(
                    $context,
                    'instagram',
                    (string) $instagram['id'],
                    $instagram['username'] ?? 'Instagram Professional Account',
                    $instagram['username'] ?? null,
                    $pageToken,
                    null,
                    null,
                    ['instagram_basic', 'instagram_content_publish']
                );
            }
        }

        if (empty($pages)) {
            throw new \RuntimeException(
                'No accessible Facebook Pages were returned. Check app permissions and Page access.'
            );
        }
    }

    private function completeTikTok(string $code, array $context): void
    {
        $config = config('services.tiktok');

        $tokens = Http::asForm()
            ->post('https://open.tiktokapis.com/v2/oauth/token/', [
                'client_key' => $config['client_key'],
                'client_secret' => $config['client_secret'],
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $config['redirect_uri'],
            ])
            ->throw()
            ->json();

        if (empty($tokens['access_token']) || empty($tokens['open_id'])) {
            throw new \RuntimeException('TikTok token response was incomplete.');
        }

        $profile = Http::withToken($tokens['access_token'])
            ->get('https://open.tiktokapis.com/v2/user/info/', [
                'fields' => 'open_id,display_name,username,avatar_url',
            ])
            ->throw()
            ->json('data.user', []);

        $this->saveAccount(
            $context,
            'tiktok',
            (string) $tokens['open_id'],
            $profile['display_name'] ?? 'TikTok Account',
            $profile['username'] ?? null,
            $tokens['access_token'],
            $tokens['refresh_token'] ?? null,
            isset($tokens['expires_in']) ? (int) $tokens['expires_in'] : null,
            isset($tokens['scope'])
                ? explode(',', $tokens['scope'])
                : ['user.info.basic', 'video.list']
        );
    }

    public function syncPosts(int $id)
    {
        $sellerId = DB::table('sellers')
            ->where('user_id', auth()->id())
            ->value('id');

        abort_unless($sellerId, 403, 'Seller profile not found.');

        $account = DB::table('social_accounts')
            ->where('id', $id)
            ->where('seller_id', $sellerId)
            ->where('status', 'connected')
            ->first();

        abort_unless($account, 404, 'Connected social account not found.');

        $logId = DB::table('social_sync_logs')->insertGetId([
            'seller_id' => $sellerId,
            'social_account_id' => $account->id,
            'action' => 'import_posts',
            'status' => 'pending',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $token = Crypt::decryptString($account->access_token);

            if ($account->provider === 'instagram') {
                $url = 'https://graph.facebook.com/'
                    . config('services.meta.graph_version')
                    . '/' . $account->provider_account_id . '/media';

                $items = Http::withToken($token)
                    ->get($url, [
                        'fields' => 'id,caption,media_type,media_url,permalink,timestamp',
                        'limit' => 25,
                    ])
                    ->throw()
                    ->json('data', []);

                $posts = array_map(fn ($item) => [
                    'id' => $item['id'] ?? null,
                    'caption' => $item['caption'] ?? null,
                    'type' => $item['media_type'] ?? 'media',
                    'media_url' => $item['media_url'] ?? null,
                    'permalink' => $item['permalink'] ?? null,
                    'published_at' => $item['timestamp'] ?? null,
                ], $items);

            } elseif ($account->provider === 'facebook') {
                $url = 'https://graph.facebook.com/'
                    . config('services.meta.graph_version')
                    . '/' . $account->provider_account_id . '/posts';

                $items = Http::withToken($token)
                    ->get($url, [
                        'fields' => 'id,message,permalink_url,created_time,attachments{media_type,media,url}',
                        'limit' => 25,
                    ])
                    ->throw()
                    ->json('data', []);

                $posts = array_map(fn ($item) => [
                    'id' => $item['id'] ?? null,
                    'caption' => $item['message'] ?? null,
                    'type' => $item['attachments']['data'][0]['media_type'] ?? 'post',
                    'media_url' => $item['attachments']['data'][0]['media']['image']['src']
                        ?? $item['attachments']['data'][0]['url']
                        ?? null,
                    'permalink' => $item['permalink_url'] ?? null,
                    'published_at' => $item['created_time'] ?? null,
                ], $items);

            } elseif ($account->provider === 'tiktok') {
                $response = Http::withToken($token)
                    ->post(
                        'https://open.tiktokapis.com/v2/video/list/?fields=id,title,video_description,create_time,share_url,cover_image_url',
                        ['max_count' => 20]
                    )
                    ->throw()
                    ->json('data.videos', []);

                $posts = array_map(fn ($item) => [
                    'id' => $item['id'] ?? null,
                    'caption' => $item['video_description']
                        ?? $item['title']
                        ?? null,
                    'type' => 'video',
                    'media_url' => $item['cover_image_url'] ?? null,
                    'permalink' => $item['share_url'] ?? null,
                    'published_at' => isset($item['create_time'])
                        ? date('Y-m-d H:i:s', (int) $item['create_time'])
                        : null,
                ], $response);

            } else {
                throw new \RuntimeException('Unsupported social provider.');
            }

            $saved = 0;

            foreach ($posts as $post) {
                if (empty($post['id'])) {
                    continue;
                }

                $where = [
                    'social_account_id' => $account->id,
                    'provider_post_id' => (string) $post['id'],
                ];

                $values = [
                    'post_type' => $post['type'],
                    'caption' => $post['caption'],
                    'media_url' => $post['media_url'],
                    'permalink' => $post['permalink'],
                    'published_at' => $post['published_at'],
                    'sync_status' => 'imported',
                    'updated_at' => now(),
                ];

                $existing = DB::table('social_posts')
                    ->where($where)
                    ->exists();

                if ($existing) {
                    DB::table('social_posts')
                        ->where($where)
                        ->update($values);
                } else {
                    DB::table('social_posts')->insert(array_merge(
                        $where,
                        $values,
                        [
                            'product_id' => null,
                            'created_at' => now(),
                        ]
                    ));
                }

                $saved++;
            }

            DB::table('social_accounts')
                ->where('id', $account->id)
                ->update([
                    'last_synced_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('social_sync_logs')
                ->where('id', $logId)
                ->update([
                    'status' => 'success',
                    'message' => "Imported or updated {$saved} posts.",
                    'metadata' => json_encode(['count' => $saved]),
                    'finished_at' => now(),
                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Social post sync completed.',
                'data' => ['synced_count' => $saved],
            ]);

        } catch (Throwable $e) {
            Log::error('Social post sync failed', [
                'seller_id' => $sellerId,
                'social_account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            DB::table('social_sync_logs')
                ->where('id', $logId)
                ->update([
                    'status' => 'failed',
                    'message' => 'Provider sync failed. Check server logs.',
                    'finished_at' => now(),
                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => false,
                'message' => 'Social sync failed. Check provider permissions and server logs.',
            ], 422);
        }
    }

}
