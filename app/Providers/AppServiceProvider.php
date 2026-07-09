<?php

namespace App\Providers;

use App\Jobs\NotifyAdminsOfNewUser;
use App\Listeners\EmailSentListener;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        require_once app_path('Support/helpers.php');
        $bindingFn = require app_path('Support/bindings.php');
        if (is_callable($bindingFn)) {
            $bindingFn();
        }

        // register events
        Event::listen(MessageSending::class, EmailSentListener::class);
        Event::listen(Verified::class, function (Verified $event) {
            NotifyAdminsOfNewUser::dispatch($event->user);
        });

        // pass locale to all views
        View::composer('*', function ($view) {
            $view->with('locale', app()->getLocale());
        });

        // force http and root url when testing using ngrok
        if (config('app.env') === 'ngrok') {
            URL::forceScheme('https');
            URL::forceRootUrl(config('app.url'));
        }
    }
}
