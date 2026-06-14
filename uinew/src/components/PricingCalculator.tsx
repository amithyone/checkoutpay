import { useState, useEffect } from "react";
import { CheckCircle2, TrendingUp, DollarSign, Wallet, ShieldCheck, Calculator } from "lucide-react";
import { motion } from "motion/react";

export default function PricingCalculator() {
  const [calcAmount, setCalcAmount] = useState<number>(25000);
  const [calculatedFee, setCalculatedFee] = useState<number>(300);
  const [bankFee, setBankFee] = useState<number>(375); // 1.5% as example benchmark

  useEffect(() => {
    // CheckoutPay formula: 1% + 50 NGN
    const checkoutPayFee = Math.round((calcAmount * 0.01) + 50);
    setCalculatedFee(checkoutPayFee);
    
    // Traditional fee calculation (e.g., standard 1.5% with no capped logic)
    const traditionalFee = Math.round(calcAmount * 0.015);
    setBankFee(traditionalFee);
  }, [calcAmount]);

  const formattedMoney = (val: number) => {
    return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 0 }).format(val);
  };

  const pricingBenchmarks = [
    { amount: 1000, fee: 60 },
    { amount: 10000, fee: 150 },
    { amount: 50000, fee: 550 },
    { amount: 100000, fee: 1050 }
  ];

  return (
    <section id="pricing" className="py-24 bg-midnight-deep text-white relative overflow-hidden">
      
      {/* Decorative gradient overlay */}
      <div className="absolute top-0 right-0 w-1/2 h-full bg-gradient-to-l from-brand-primary/10 to-transparent pointer-events-none"></div>
      
      <div className="px-6 md:px-12 max-w-7xl mx-auto relative z-10">
        
        {/* Header */}
        <div className="text-center mb-20 space-y-4">
          <h2 className="text-3xl md:text-5xl font-black tracking-tight leading-none">
            Pricing
          </h2>
          <p className="text-slate-400 font-semibold text-sm max-w-sm mx-auto">
            Competitive rates. Clear transactional charges. No surprises.
          </p>
        </div>

        {/* Layout Column split */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-16 items-start">
          
          {/* Left Column: General static parameters */}
          <div className="space-y-8">
            <div className="inline-block px-4 py-1.5 bg-white/5 border border-white/10 rounded-full text-xs font-extrabold uppercase tracking-widest text-brand-electric">
              Pay As You Go
            </div>
            
            <div className="flex flex-col sm:flex-row sm:items-end gap-3">
              <span className="text-5xl sm:text-7xl font-black tracking-tighter text-white">
                1% + ₦50
              </span>
              <span className="text-slate-400 font-bold text-xs sm:text-sm mb-2 uppercase tracking-wider block">
                per successful transaction
              </span>
            </div>

            {/* Checklist elements */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
              {[
                "Unlimited matching transactions",
                "Complete api integration key set",
                "Instant hosted checkout portals",
                "Real-time webhook notifications"
              ].map((text, idx) => (
                <div key={idx} className="flex items-center gap-2.5 text-xs sm:text-sm text-slate-350">
                  <CheckCircle2 className="w-5 h-5 text-emerald-500 shrink-0" />
                  <span className="font-semibold">{text}</span>
                </div>
              ))}
            </div>

            <p className="text-xs text-slate-500 font-medium leading-relaxed pt-2">
              *Absolutely no hidden integration fees, annual licensing, setup costs or monthly server maintenance parameters. You only pay for transactions that actually succeed.
            </p>

            <a 
              href="#register"
              className="w-full inline-block text-center bg-brand-primary hover:bg-brand-secondary text-white py-4 rounded-xl font-bold text-sm tracking-wide transition-all shadow-lg active:scale-98"
            >
              Get started with free setup
            </a>
          </div>

          {/* Right Column: Premium Interactive Fee Calculator Widget */}
          <div className="bg-white/5 border border-white/10 rounded-3xl p-6 md:p-8 space-y-6">
            
            <div className="flex justify-between items-center border-b border-white/10 pb-4">
              <div className="flex items-center gap-2">
                <Calculator className="w-5 h-5 text-brand-electric animate-bounce" />
                <h3 className="text-base font-bold font-sans">Interactive Calculator</h3>
              </div>
              <span className="bg-[#22C55E]/10 text-[#22C55E] border border-[#22C55E]/20 text-[10px] font-bold px-2 py-0.5 rounded-md">
                Compare Live Savings
              </span>
            </div>

            {/* Range Slider for calculation */}
            <div className="space-y-4">
              <div className="flex justify-between items-center text-xs">
                <span className="text-slate-400 font-semibold uppercase">Transact size</span>
                <span className="font-extrabold text-white text-base bg-white/10 px-3 py-1.5 rounded-lg border border-white/10 font-mono">
                  {formattedMoney(calcAmount)}
                </span>
              </div>

              <input 
                type="range"
                min="500"
                max="500000"
                step="500"
                value={calcAmount}
                onChange={(e) => setCalcAmount(parseInt(e.target.value) || 500)}
                className="w-full accent-brand-electric rounded-lg cursor-pointer h-2 bg-slate-800"
              />

              <div className="flex justify-between text-[10px] text-slate-500 font-bold uppercase tracking-wider">
                <span>₦500 NGN</span>
                <span>₦500K NGN</span>
              </div>
            </div>

            {/* Results comparison card */}
            <div className="grid grid-cols-2 gap-4">
              <div className="bg-white/[0.03] border border-white/5 p-4 rounded-2xl text-center space-y-1">
                <p className="text-[10px] uppercase font-extrabold tracking-wider text-slate-400">CheckoutPay Fee</p>
                <p className="text-lg font-black text-[#22C55E]">{formattedMoney(calculatedFee)}</p>
                <p className="text-[9px] text-slate-500 font-semibold">1% + ₦50</p>
              </div>

              <div className="bg-white/[0.03] border border-white/5 p-4 rounded-2xl text-center space-y-1">
                <p className="text-[10px] uppercase font-extrabold tracking-wider text-slate-400">Standard Processor</p>
                <p className="text-lg font-black text-slate-300">{formattedMoney(bankFee)}</p>
                <p className="text-[9px] text-slate-500 font-semibold">1.5% Average</p>
              </div>
            </div>

            {/* Savings Indicator */}
            <div className="bg-brand-electric/10 border border-brand-electric/20 rounded-2xl p-4 flex justify-between items-center">
              <div>
                <p className="text-[10px] text-slate-450 uppercase font-black tracking-wider leading-none">Net Savings per charge</p>
                <p className="text-xs font-bold text-slate-300 pt-1">Compared to legacy banks</p>
              </div>
              <div className="text-right">
                <span className="text-lg sm:text-xl font-black text-brand-electric">
                  {calculatedFee < bankFee ? formattedMoney(bankFee - calculatedFee) : 'Free setup'}
                </span>
              </div>
            </div>

            {/* Historic Benchmark parameters */}
            <div className="pt-4 border-t border-white/10 space-y-3.5">
              <p className="text-xs font-bold text-slate-400 uppercase tracking-widest text-center">Static pricing benchmarks</p>
              <div className="space-y-2 text-xs">
                {pricingBenchmarks.map((bench, idx) => (
                  <div key={idx} className="flex justify-between items-center py-2 border-b border-white/[0.04] last:border-0">
                    <span className="text-slate-400 text-xs font-semibold">Transaction size: {formattedMoney(bench.amount)}</span>
                    <span className="font-extrabold text-white text-xs">Total Fee: {formattedMoney(bench.fee)}</span>
                  </div>
                ))}
              </div>
            </div>

          </div>

        </div>

      </div>
    </section>
  );
}
