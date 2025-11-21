<?php

namespace App\Http\Controllers;

use App\Models\SwiftMessage;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        // 1. Fetch from MongoDB with Pagination
        // Sort by 'created_at' (Database Timestamp) descending
        $paginatedMessages = SwiftMessage::orderBy('created_at', 'desc')
            ->orderBy('_meta_type', 'asc')
            ->paginate(100);

        // 2. Group the current page's collection by Database Date
        $groupedMessages = $paginatedMessages->getCollection()
            ->groupBy(function ($message) {
                // Group by YYYY-MM-DD from the DB timestamp
                return $message->created_at->format('Y-m-d');
            })
            ->map(function ($dateGroup) {
                // Inside each date, group by Message Type (e.g., 103, 541)
                return $dateGroup->groupBy('_meta_type');
            });

        return view('dashboard', [
            'groupedMessages' => $groupedMessages,
            'paginator' => $paginatedMessages
        ]);
    }
}