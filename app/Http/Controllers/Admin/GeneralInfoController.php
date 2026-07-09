<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GeneralInfo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class GeneralInfoController extends Controller
{
    public static string $name = 'general-info';
    public static string $icon = "Info";

    private $allowedColumns = ['id', 'sponsors', 'contact', 'social_media', 'social_media_to_follow', 'twibbon_url', 'payment_methods'];
    private $fillableColumns = ['sponsors', 'contact', 'social_media', 'social_media_to_follow', 'twibbon_url', 'payment_methods'];

    private function getPaginatedData(Request $request): array
    {
        return
            getPaginatedData(
                $request,
                GeneralInfo::class,
                $this->allowedColumns,
                'admin.general-info.index',
            );
    }

    public function index(Request $request): Response
    {
        return inertia('Admin/GeneralInfo', $this->getPaginatedData($request));
    }

    public function edit(Request $request, GeneralInfo $generalInfo): Response
    {
        return inertia('Admin/GeneralInfo', array_merge($this->getPaginatedData($request), [
            'editData' => $generalInfo
                ->only($this->allowedColumns),
        ]));
    }

    public function show(Request $request, GeneralInfo $generalInfo): Response
    {
        return inertia('Admin/GeneralInfo', array_merge($this->getPaginatedData($request), [
            'showData' => $generalInfo
                ->only($this->allowedColumns),
        ]));
    }

    public function update(Request $request, GeneralInfo $generalInfo): RedirectResponse
    {
        $generalInfo->update($this->validateAndStoreFile($request, $generalInfo));
        return back()->with('success', "General Info updated successfully");
    }

    private function validateAndStoreFile(Request $request, GeneralInfo $generalInfo): array
    {
        $request->validate([
            'sponsors' => 'required|array',
            'sponsors.*.name' => 'required|string',
            'sponsors.*.url' => 'required|url',
            'sponsors.*.image' => 'required|string',
            'contact' => 'required|array',
            'contact.email' => 'required|string',
            'contact.phoneNumber' => 'required|numeric',
            'social_media' => 'required|array',
            'social_media_to_follow' => 'required|array',
            'twibbon_url' => 'required|string',
            'payment_methods' => 'required|array',
            'payment_methods.*.method' => 'required|string',
            'payment_methods.*.accountNumber' => 'required|string',
            'payment_methods.*.holderName' => 'required|string', 
        ], [], [
            'sponsors.*.name' => 'Name',
            'sponsors.*.url' => 'URL',
            'sponsors.*.image' => 'Image',
            'social_media' => 'Social Media',
            'contact.email' => 'Email',
            'contact.phoneNumber' => 'Phone Number',
        ]);

        $dataToUpdate = $request->only($this->fillableColumns);
        syncFileStorage($generalInfo, $dataToUpdate, "sponsors.*.image", 'images/sponsors');
        return $dataToUpdate;
    }
}
