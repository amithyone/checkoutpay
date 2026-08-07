<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class InvestorPitchController extends Controller
{
    public function show(): View
    {
        return view('investor.pitch', $this->pitchData());
    }

    public function summary(): View
    {
        return view('investor.summary', $this->pitchData());
    }

    /**
     * @return array{photos: array<string, array<string, mixed>>, metrics: array<string, string>, funds: list<array{label: string, pct: int, tone: string}>}
     */
    private function pitchData(): array
    {
        $slots = [
            'hero' => [
                'file' => 'hero.jpg',
                'label' => 'Hero photo',
                'hint' => 'Full-bleed: till, phone, or retail floor',
                'aspect' => '21 / 9',
            ],
            'product_pay' => [
                'file' => 'proximity-pay.jpg',
                'label' => 'Proximity Pay in action',
                'hint' => 'Customer phone near Cheko / till',
                'aspect' => '4 / 3',
            ],
            'product_cheko' => [
                'file' => 'cheko-pos.jpg',
                'label' => 'Cheko Windows POS',
                'hint' => 'Supermarket, hotel, or retail counter',
                'aspect' => '4 / 3',
            ],
            'product_apps' => [
                'file' => 'checkoutnow-apps.jpg',
                'label' => 'CheckoutNow apps',
                'hint' => 'Phone mockups / App Store screenshots',
                'aspect' => '4 / 3',
            ],
            'team' => [
                'file' => 'team.jpg',
                'label' => 'Team photo',
                'hint' => 'Founders or core team',
                'aspect' => '16 / 9',
            ],
            'retail' => [
                'file' => 'retail-density.jpg',
                'label' => 'Merchant / retail scene',
                'hint' => 'Partner shop or pilot location',
                'aspect' => '16 / 9',
            ],
            'ask_bg' => [
                'file' => 'ask-background.jpg',
                'label' => 'Closing section photo',
                'hint' => 'Optional atmospheric image behind the ask',
                'aspect' => '21 / 9',
            ],
        ];

        $photos = [];
        foreach ($slots as $key => $slot) {
            $relative = 'images/investor/'.$slot['file'];
            $absolute = public_path($relative);
            $photos[$key] = array_merge($slot, [
                'path' => $relative,
                'url' => file_exists($absolute) ? asset($relative).'?v='.filemtime($absolute) : null,
            ]);
        }

        return [
            'photos' => $photos,
            'metrics' => [
                'volume' => '₦700M+',
                'daily' => '~₦3M',
                'merchants' => '~70',
                'wallets' => '700+',
                'runrate' => '~₦1.1B',
            ],
            'funds' => [
                ['label' => 'Cheko + contactless', 'pct' => 35, 'tone' => '#0B3D91'],
                ['label' => 'Compliance & licensing', 'pct' => 25, 'tone' => '#1A6BB5'],
                ['label' => 'Credit liquidity*', 'pct' => 20, 'tone' => '#3D9AD1'],
                ['label' => 'Market / growth', 'pct' => 20, 'tone' => '#7EC4E8'],
            ],
        ];
    }
}
