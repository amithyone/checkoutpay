<?php

namespace App\Console\Commands;

use App\Services\MevonRubiesVirtualAccountService;
use App\Services\Whatsapp\PhoneNormalizer;
use Illuminate\Console\Command;

/**
 * Exercise Mevon Rubies POST createrubies (action=create, account_type=personal) — same shape as WhatsApp Tier 2 KYC.
 */
class TestMevonRubiesInitiateCommand extends Command
{
    protected $signature = 'mevon:rubies-test-initiate
        {--fname=John : First name}
        {--lname=Doe : Last name}
        {--phone=08012345678 : Nigerian phone local 081… or 234…}
        {--dob=1990-01-01 : Date of birth YYYY-MM-DD}
        {--email=test@example.com : Email}
        {--bvn= : 11-digit BVN (required unless --nin)}
        {--nin= : 11-digit NIN (required unless --bvn)}';

    protected $description = 'Call Mevon Rubies createrubies create (Tier 2, no OTP); prints parsed result or HTTP/API error.';

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

        $bvnOpt = (string) $this->option('bvn');
        $ninOpt = (string) $this->option('nin');
        $bvn = preg_replace('/\D+/', '', $bvnOpt) ?? '';
        $nin = preg_replace('/\D+/', '', $ninOpt) ?? '';
        if (strlen($bvn) !== 11 && strlen($nin) !== 11) {
            $this->error('Provide exactly one of --bvn= (11 digits) or --nin= (11 digits).');

            return self::FAILURE;
        }

        $fname = (string) $this->option('fname');
        $lname = (string) $this->option('lname');
        $dob = trim((string) $this->option('dob'));
        $email = strtolower(trim((string) $this->option('email')));

        $url = rtrim((string) (config('services.mevonrubies.base_url') ?: config('services.mevonpay.base_url', '')), '/')
            .'/'.ltrim((string) config('services.mevonrubies.create_path', '/V1/createrubies'), '/');
        $this->info("Endpoint: {$url}");
        $this->info("Payload: action=create, account_type=personal, fname={$fname}, lname={$lname}, phone_local={$local11}, dob={$dob}, email={$email}, bvn/nin=***");

        try {
            $out = $rubies->createRubiesPersonalAccount(
                $fname,
                $lname,
                $local11,
                $dob,
                $email,
                strlen($bvn) === 11 ? $bvn : null,
                strlen($nin) === 11 ? $nin : null,
            );
            $this->info('Provider accepted the request. Parsed payload:');
            $this->line(json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
