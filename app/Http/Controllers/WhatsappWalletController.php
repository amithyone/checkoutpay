<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class WhatsappWalletController extends Controller
{
    public function index(): View
    {
        return view('whatsapp-wallet.index');
    }
}
