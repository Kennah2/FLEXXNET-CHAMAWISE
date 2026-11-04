<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $organizations = $request->user()->organizations()->orderBy('name')->get();

        return view('dashboard', compact('organizations'));
    }
}
