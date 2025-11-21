<?php

namespace App\Http\Controllers;

use App\Models\SwiftMessage;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        // 1. Fetch latest data from MongoDB
        $messages = SwiftMessage::orderBy('created_at', 'desc')->get();

        // 2. Create Hierarchy
        // Layer 1: Group by System Date (YYYY-MM-DD)
        $groupedMessages = $messages->groupBy(function($item) {
            return $item->created_at->format('Y-m-d'); 
        })->map(function($dateGroup) {
            // Layer 2: Group by Message Type (e.g. "103")
            return $dateGroup->groupBy('_meta_type');
        });

        // 3. Pagination (Manual Paginator for grouped collection)
        // Note: For simple display, passing the whole collection is fine. 
        // If you have thousands of rows, we can adjust this to paginate properly.
        
        return view('dashboard', [
            'groupedMessages' => $groupedMessages
        ]);
    }
}