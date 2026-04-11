<?php

namespace App\Console\Commands;

use App\Services\MevonRubiesVirtualAccountService;
use App\Services\Whatsapp\PhoneNormalizer;
use Illuminate\Console\Command;

/**
 * Exercise Mevon Rubies POST createrubies (action=initiate) with the same payload shape as WhatsApp Tier 2 KYC.
 */
class TestMevonRubiesInitiateCommand extends Command
{
    protected $signature = 'mevon:rubies-test-initiate
        {--fname=Innocent : First name}
        {--lname=Solomon : Last name}
        {--gender=male : male or female}
        {--phone=2348148790554 : Nigerian phone as 234… E.164 digits or local 081…}
        {--bvn=22377512104 : 11-digit BVN}';

    protected $description = 'Call Mevon Rubies createrubies initiate (Tier 2); prints parsed result or HTTP/API error from the provider.';

    public function handle(MevonRubiesVirtualAccountService $rubies): int
    {
        if (! $rubies->isConfigured()) {
            $this->error('MevonRubies is not configured. Set MEVONRUBIES_BASE_URL and MEVONRUBIES_SECRET_KEY (or MEVONPAY_* fallback).');

            return self::FAILURE;
        }

        $phoneOpt = (string) $this->option('phone');
        $d = preg_replace('/\D+/', '', $phoneOpt) ?? '';
        $local11 = null;
        if (strlen($d) === 11 && str_starts_with($d, '0')) {
            $local11 = $d;
        } elseif (strlen($d) === 13 && str_starts_with($d, '234')) {
            $local11 = PhoneNormalizer::e164DigitsToNgLocal11($d);
        }
        if ($local11 === null) {
            $this->error('Could not normalize --phone to Nigerian local 11 (081…). Use 234XXXXXXXXXXX or 081XXXXXXXXX.');

            return self::FAILURE;
        }

        $bvn = preg_replace('/\D+/', '', (string) $this->option('bvn')) ?? '';
        if (strlen($bvn) !== 11) {
            $this->error('BVN must be exactly 11 digits.');

            return self::FAILURE;
        }

        $fname = (string) $this->option('fname');
        $lname = (string) $this->option('lname');
        $gender = strtolower((string) $this->option('gender'));
        if (! in_array($gender, ['male', 'female'], true)) {
            $this->error('--gender must be male or female.');

            return self::FAILURE;
        }

        $url = rtrim((string) (config('services.mevonrubies.base_url') ?: config('services.mevonpay.base_url', '')), '/')
            .'/'.ltrim((string) config('services.mevonrubies.create_path', '/V1/createrubies'), '/');
        $this->info("Endpoint: {$url}");
        $this->info("Payload: fname={$fname}, lname={$lname}, gender={$gender}, phone_local={$local11}, bvn=***********");

        try {
            $out = $rubies->initiateRubiesAccount($fname, $lname, $gender, $local11, $bvn);
            $this->info('Provider accepted the request. Parsed payload:');
            $this->line(json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
