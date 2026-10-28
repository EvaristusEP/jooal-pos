<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RoleDashboardController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        if ($request->user()->isOwner()) {
            return redirect()->route('dashboard.owner');
        }

        if ($request->user()->isInventory()) {
            return redirect()->route('inventory.stocks.index');
        }

        if ($request->user()->isManager() || $request->user()->isCashier()) {
            return redirect()->route('dashboard.pos');
        }

        abort(403);
    }
}
