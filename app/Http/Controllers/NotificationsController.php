<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class NotificationsController extends Controller
{
    private $allowedColumns = ["id", "data", "read_at", "created_at"];

    /**
     * Give a count of unread notifications and the latest notification
     *  
     * @return JsonResponse
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $unread = $request->user()->unreadNotifications();
        $latest = $unread->latest()->select($this->allowedColumns)->first();

        return response()->json([
            'count' => $unread->count(),
            'latest' => $latest ?? null
        ]);
    }

    /**
     * Mark all notifications as read
     * 
     * @return \Illuminate\Http\Response
     */
    public function markAllAsRead(Request $request): Response
    {
        $request->user()->unreadNotifications?->markAsRead();
        return response([], Response::HTTP_NO_CONTENT);
    }

    /**
     * Mark a specific notification as read
     * 
     * @param int|string $id
     * @return \Illuminate\Http\Response
     */
    public function markAsRead(Request $request, int|string $id): Response
    {
        $request->user()->unreadNotifications()?->find($id)?->markAsRead();
        return response([], Response::HTTP_NO_CONTENT);
    }

    /**
     * Delete all notifications
     * 
     * @return \Illuminate\Http\Response
     */
    public function deleteAll(Request $request): Response
    {
        $request->user()->notifications()->delete();
        return response([], Response::HTTP_NO_CONTENT);
    }

    /**
     * Delete a specific notification
     * 
     * @param int|string $id
     * @return \Illuminate\Http\Response
     */
    public function deleteNotification(Request $request, int|string $id): Response
    {
        $request->user()->notifications()->find($id)?->delete();
        return response([], Response::HTTP_NO_CONTENT);
    }


    /**
     * Get all notifications but paginated
     * 
     * @return JsonResponse
     */
    public function getNotifications(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()
            ->select($this->allowedColumns)
            ->orderBy('created_at', 'desc')
            ->cursorPaginate(10);

        return response()->json($notifications);
    }
}
