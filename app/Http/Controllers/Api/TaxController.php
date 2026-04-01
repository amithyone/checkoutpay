<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaxController extends Controller
{
    /**
     * Save Business Tax Record
     */
    public function saveBusiness(Request $request)
    {
        $data = $request->json()->all();

        $business_name = $data['business_name'] ?? 'Unknown';
        
        $raw_phone = $data['phone_number'] ?? '';
        $raw_phone = preg_replace('/[^0-9+]/', '', $raw_phone);
        if (strpos($raw_phone, '0') === 0 && strlen($raw_phone) === 11) {
            $raw_phone = '+234' . substr($raw_phone, 1);
        } elseif (strpos($raw_phone, '234') === 0) {
            $raw_phone = '+' . $raw_phone;
        }
        $phone_number = $raw_phone;
        
        $rc_number = $data['rc_number'] ?? '';
        $tin_number = $data['tin_number'] ?? '';
        $address = $data['address'] ?? '';
        $statement_filename = $data['statement_filename'] ?? '';
        $total_inflows = (float)($data['total_inflows'] ?? 0);
        $total_outflows = (float)($data['total_outflows'] ?? 0);
        $declared_profit_perc = (float)($data['declared_profit_perc'] ?? 0);
        $assessable_profit = (float)($data['assessable_profit'] ?? 0);
        $cit_amount = (float)($data['cit_amount'] ?? 0);
        $dev_levy_amount = (float)($data['dev_levy_amount'] ?? 0);
        $total_tax_due = (float)($data['total_tax_due'] ?? 0);
        $is_small_company = (int)($data['is_small_company'] ?? 0);

        // Deduplication check
        if (!empty($rc_number) && !empty($phone_number)) {
            $existing = DB::table('nigtax_business_records')
                ->where('rc_number', $rc_number)
                ->where('phone_number', $phone_number)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => true,
                    'id' => $existing->id,
                    'message' => 'Duplicate prevented. Using existing record.'
                ]);
            }
        }

        $id = DB::table('nigtax_business_records')->insertGetId([
            'business_name' => $business_name,
            'phone_number' => $phone_number,
            'rc_number' => $rc_number,
            'tin_number' => $tin_number,
            'address' => $address,
            'statement_filename' => $statement_filename,
            'total_inflows' => $total_inflows,
            'total_outflows' => $total_outflows,
            'declared_profit_perc' => $declared_profit_perc,
            'assessable_profit' => $assessable_profit,
            'cit_amount' => $cit_amount,
            'dev_levy_amount' => $dev_levy_amount,
            'total_tax_due' => $total_tax_due,
            'is_small_company' => $is_small_company
        ]);

        return response()->json(['success' => true, 'id' => $id]);
    }

    /**
     * Save Personal Income Tax Record
     */
    public function savePersonal(Request $request)
    {
        $data = $request->json()->all();

        $individual_name = $data['individual_name'] ?? 'Unknown';
        $annual_income = (float)($data['annual_income'] ?? 0);
        $pension = (float)($data['pension'] ?? 0);
        $nhf = (float)($data['nhf'] ?? 0);
        $nhis = (float)($data['nhis'] ?? 0);
        $life_assurance = (float)($data['life_assurance'] ?? 0);
        $rent_relief = (float)($data['rent_relief'] ?? 0);
        $total_tax_due = (float)($data['total_tax_due'] ?? 0);

        $id = DB::table('nigtax_personal_records')->insertGetId([
            'individual_name' => $individual_name,
            'annual_income' => $annual_income,
            'pension' => $pension,
            'nhf' => $nhf,
            'nhis' => $nhis,
            'life_assurance' => $life_assurance,
            'rent_relief' => $rent_relief,
            'total_tax_due' => $total_tax_due
        ]);

        return response()->json(['success' => true, 'id' => $id]);
    }
}
