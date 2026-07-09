<?php

namespace App\Http\Controllers;

use App\Models\Competition;
use App\Models\Faq;
use App\Models\GeneralInfo;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class StaticController extends Controller
{
    /**
     * Get the user's preferred language and redirect to the corresponding landing page
     * 
     * @return RedirectResponse
     */
    public function detectLanguage(Request $request): RedirectResponse
    {
        // Get the browser's preferred language
        $preferredLanguage = substr($request->header('Accept-Language'), 0, 2);
        $supportedLanguages = app('supportedLocales')['active'];

        //redirect to preferred language landing page;
        return to_route('home', ['locale' => in_array($preferredLanguage, $supportedLanguages) ? $preferredLanguage : $supportedLanguages[0]]);
    }

    /**
     * Get the data to be passed to the view
     * 
     * @return array
     */

    protected function getData(bool $isHome = false): array
    {
        return [
            'generalInfo' => Cache::rememberForever('general_info', fn() => GeneralInfo::first()),
            'competitions' => $isHome ?
                Cache::rememberForever('competitions_home', fn() => Competition::select('name', 'image', 'short_desc', 'timeline')->get()) :
                Cache::rememberForever('competitions', fn() => Competition::select('id', 'name', 'updated_at')->get()),
        ];
    }

    /**
     * Show the home page
     * 
     * @return View
     */
    public function home(): View
    {
        return view('home', $this->getData(isHome: true));
    }

    /**
     * Show the competition page
     * 
     * @param string $name
     * @return View
     */
    public function competition(string $name): View
    {
        $competition = Cache::rememberForever('competition_' . $name, function () use ($name) {
            return Competition::where('name', $name)->first();
        });       
        if (!$competition) abort(404);
        
        return view('competition', [
            ...$this->getData(),
            'competition' => $competition
        ]);
    }

    /**
     * Show the faq page
     * 
     * @return View
     */
    public function faq(): View
    {
        $faqs = Cache::rememberForever(
            'faq',
            fn()  => Faq::with('competition')->get()
                ->map(fn($faq) => [
                    'id' => $faq->id,
                    'question' => $faq->question,
                    'answer' => $faq->answer,
                    'competition_name' => $faq->competition?->name,
                ])
        );
        return view('faq', [
            ...$this->getData(),
            'faqs' => $faqs
        ]);
    }

    /**
     * Show the about page
     * 
     * @return View
     */
    public function about(): View
    {
        return view('about', $this->getData());
    }
}
