<?php

namespace App\Jobs;

use App\Models\Competition;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class GenerateSitemapRobotsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $competitions = Cache::rememberForever('competitions', fn() => Competition::select('id', 'name', 'updated_at')->get());
        $locales = app('supportedLocales')['active'];

        $sitemapContent = '<?xml version="1.0" encoding="UTF-8"?>';
        $sitemapContent .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        // Add static pages
        $staticRoutes = ['home', 'faq', 'about'];
        foreach ($locales as $locale) {
            foreach ($staticRoutes as $routeName) {
                $sitemapContent .= '<url>';
                $sitemapContent .= '<loc>' . route($routeName, ['locale' => $locale]) . '</loc>';
                $sitemapContent .= '<lastmod>' . now()->toAtomString() . '</lastmod>';
                $sitemapContent .= '<changefreq>monthly</changefreq>';
                $sitemapContent .= '<priority>' . ($routeName === 'home' ? '1.0' : '0.9') . '</priority>';
                $sitemapContent .= '</url>';
            }
        }

        // Add dynamic competition pages
        foreach ($competitions as $competition) {
            foreach ($locales as $locale) {
                Log::info('Generating sitemap for competition ' . $competition->name . ' update at:' . $competition->updated_at);
                $sitemapContent .= '<url>';
                $sitemapContent .= '<loc>' . route('competition', ['locale' => $locale, 'name' => $competition->name]) . '</loc>';
                $sitemapContent .= '<lastmod>' . $competition?->updated_at?->toAtomString() . '</lastmod>';
                $sitemapContent .= '<changefreq>weekly</changefreq>';
                $sitemapContent .= '<priority>0.9</priority>';
                $sitemapContent .= '</url>';
            }
        }

        $sitemapContent .= '</urlset>';

        // Write the string to the public/sitemap.xml file
        File::put(public_path('sitemap.xml'), $sitemapContent);

        // Write the robots.txt file
        if (app()->environment('production')) {
            // If the environment is 'production', create the real robots.txt
            $robotsContent = implode(PHP_EOL, [
                'User-agent: *',
                'Disallow: /*/email/*',
                'Disallow: /*/auth/*',
                'Disallow: /notifications/*',
                'Disallow: /institution-card/*',
                'Disallow: /follow-proof/*',
                'Disallow: /twibbon-proof/*',
                'Disallow: /payment-proof/*',
                'Disallow: /submission/*',
                'Disallow: /feedback/*',
                '',
                'Sitemap: ' . config('app.url') . '/sitemap.xml'
            ]);
        } else {
            // For any other environment (local, staging), block all crawlers
            $robotsContent = implode(PHP_EOL, [
                'User-agent: *',
                'Disallow: /'
            ]);
        }
        File::put(
            public_path('robots.txt'), $robotsContent);
    }
}
