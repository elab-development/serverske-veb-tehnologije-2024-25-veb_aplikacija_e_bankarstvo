<?php

namespace App\Http\Controllers;

use App\Models\ExchangeRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class ExchangeRateController extends Controller
{
    /**
     * Admin-only: sinhronizuj kurs iz javnog API-ja ili upiši ručno ako je prosleđen "rate".
     * Body primer:
     *  { "base":"RSD", "quote":"EUR", "date":"2025-10-16", "source":"frankfurter" }
     *  ili ručno: { "base":"RSD","quote":"EUR","date":"2025-10-16","rate":0.00853 }
     */
    public function sync(Request $req)
    {
        $data = $req->validate([
            'base'   => 'required|string|size:3',
            'quote'  => 'required|string|size:3|different:base',
            'date'   => 'nullable|date',
            'source' => 'nullable|in:frankfurter',
            'rate'   => 'nullable|numeric|min:0.00000001',
        ]);

        $base  = strtoupper($data['base']);
        $quote = strtoupper($data['quote']);
        $date  = $data['date'] ?? Carbon::today()->toDateString();

        // Ako je prosleđen ručni rate, preskoči API
        if (!empty($data['rate'])) {
            $rate = (float)$data['rate'];
        } else {
            // Javni API: Frankfurter (ECB)
            if (($data['source'] ?? null) === 'frankfurter') {
                $resp = Http::acceptJson()->get("https://api.frankfurter.app/{$date}", [
                    'from' => $base,
                    'to'   => $quote,
                ]);
                if ($resp->failed()) {
                    return response()->json(['message'=>'Neuspešan poziv javnog API-ja','details'=>$resp->json()], 502);
                }
                $json = $resp->json();
                $rate = $json['rates'][$quote] ?? null;
                if (!$rate) {
                    return response()->json(['message'=>"Nije pronađen kurs za $base/$quote na $date"], 422);
                }
            } else {
                return response()->json(['message'=>'Nije prosleđen rate niti izvor (source)'], 422);
            }
        }

        $er = ExchangeRate::updateOrCreate(
            ['base'=>$base, 'quote'=>$quote, 'rate_date'=>$date],
            ['rate'=>$rate]
        );

        return response()->json(['status'=>'ok','exchange_rate'=>$er], 201);
    }
}
