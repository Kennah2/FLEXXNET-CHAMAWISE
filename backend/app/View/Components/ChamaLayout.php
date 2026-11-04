<?php

namespace App\View\Components;

use App\Models\Organization;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * The authenticated, sidebar-based shell for pages scoped to one chama (Overview, Members,
 * and the per-module pages). Distinct from <x-app-layout>, which is the lighter top-nav shell
 * used for cross-chama pages (the chama picker dashboard, account profile).
 */
class ChamaLayout extends Component
{
    public function __construct(
        public Organization $organization,
        public string $active = '',
    ) {}

    public function render(): View
    {
        return view('layouts.chama');
    }
}
