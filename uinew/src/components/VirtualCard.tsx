import { useState, useEffect } from "react";
import { 
  CheckCircle2, 
  Smartphone, 
  RefreshCw, 
  ArrowRightLeft, 
  CreditCard,
  TrendingUp,
  Download,
  Flame,
  Globe2
} from "lucide-react";
import { motion } from "motion/react";

export default function VirtualCard() {
  const [calcMode, setCalcMode] = useState<'fund' | 'withdraw'>('fund');
  const [usdAmount, setUsdAmount] = useState<number>(10);
  const [ngnAmount, setNgnAmount] = useState<number>(13931);

  const fundingRate = 1393;
  const withdrawRate = 1348;
  const cardSetupFee = 7.50; // USD 
  const freeStartingBalance = 5.00; // USD

  // Calculate NGN whenever USD or CalcMode changes
  useEffect(() => {
    const rate = calcMode === 'fund' ? fundingRate : withdrawRate;
    const computed = Math.round(usdAmount * rate);
    setNgnAmount(computed);
  }, [usdAmount, calcMode]);

  const handleNgnChange = (val: number) => {
    const rate = calcMode === 'fund' ? fundingRate : withdrawRate;
    const usd = parseFloat((val / rate).toFixed(2)) || 0;
    setUsdAmount(usd);
    setNgnAmount(val);
  };

  const formattedCurrency = (val: number, currency: 'USD' | 'NGN') => {
    if (currency === 'USD') {
      return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(val);
    } else {
      return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 0 }).format(val);
    }
  };

  return (
    <section id="virtual-card" className="py-24 px-6 md:px-12 max-w-7xl mx-auto">
      <div className="mb-12">
        <span className="text-brand-primary font-bold text-xs flex items-center gap-2 mb-3 uppercase tracking-wider">
          <Globe2 className="w-4 h-4 text-brand-electric" /> New in CheckoutPay
        </span>
        <h2 className="text-3xl md:text-5xl font-black text-midnight-deep tracking-tight">
          Dollar Virtual Card
        </h2>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
        
        {/* Left Column - Information with app download options */}
        <div className="lg:col-span-5 space-y-8">
          <p className="text-slate-600 font-medium leading-relaxed text-sm md:text-base">
            Shop globally with a USD virtual card funded from your NGN wallet. Pay for subscriptions (Netflix, Spotify, Prime), international social ads, and global checkout channels — then withdraw unused dollars instantly back to your naira wallet when done.
          </p>

          {/* Key Bullet Features */}
          <ul className="space-y-4">
            {[
              "Instant card delivery in the CheckoutNow mobile app",
              "Fund, freeze, pause, and withdraw cash completely from your phone",
              "Transparent USD sell/buy rates with zero hidden FX spreads",
              "Starting balance included when your virtual card is initialized"
            ].map((text, idx) => (
              <li key={idx} className="flex items-start gap-3">
                <CheckCircle2 className="w-5 h-5 text-brand-electric shrink-0 mt-0.5" />
                <span className="text-slate-700 text-sm font-semibold">{text}</span>
              </li>
            ))}
          </ul>

          {/* App Store & Google Play Store Downloads */}
          <div className="p-6 bg-slate-50 rounded-2xl border border-slate-200/60 space-y-4">
            <div className="space-y-1">
              <span className="bg-brand-primary/10 text-brand-primary text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-md">
                CheckoutNow App Available
              </span>
              <p className="text-xs text-slate-500 font-semibold pt-1">
                Virtual card services are fully optimized inside our official native applications.
              </p>
            </div>

            <div className="flex flex-col sm:flex-row gap-3">
              {/* App Store Badge */}
              <a 
                href="#appstore"
                onClick={(e) => { e.preventDefault(); alert("CheckoutNow is processing App Store launch with METRAVON innovation. Registered beta users can download directly from TestFlight."); }}
                className="flex-1 flex items-center gap-3 bg-midnight-deep hover:bg-slate-850 text-white px-4 py-3 rounded-xl transition-all shadow-md group border border-slate-800"
              >
                {/* Custom App Store Look */}
                <svg className="w-6 h-6 text-white group-hover:scale-105 transition-transform shrink-0" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M18.71,19.5C17.88,20.74 17,21.95 15.66,21.97C14.32,22 13.89,21.18 12.37,21.18C10.84,21.18 10.37,21.95 9.1,22C7.79,22.05 6.8,20.68 5.96,19.47C4.25,17 2.94,12.45 4.7,9.39C5.57,7.87 7.13,6.91 8.82,6.88C10.1,6.86 11.32,7.75 12.11,7.75C12.89,7.75 14.37,6.68 15.92,6.84C16.57,6.87 18.39,7.1 19.56,8.82C19.47,8.88 17.39,10.1 17.41,12.63C17.44,15.65 20.06,16.66 20.1,16.67C20.08,16.74 19.67,18.11 18.71,19.5M15.97,4.17C16.63,3.37 17.07,2.28 16.95,1C16,1.04 14.9,1.6 14.24,2.38C13.68,3.04 13.19,4.14 13.34,5.39C14.39,5.47 15.4,4.88 15.97,4.17Z" />
                </svg>
                <div className="text-left">
                  <p className="text-[9px] text-slate-400 uppercase font-bold leading-none">Download on the</p>
                  <p className="text-xs font-bold text-white font-sans leading-tight">App Store</p>
                </div>
              </a>

              {/* Google Play Badge */}
              <a 
                href="#playstore"
                onClick={(e) => { e.preventDefault(); alert("CheckoutNow is processing Google Play Store testing sandbox. APK binaries are available on your checkout merchant dashboard."); }}
                className="flex-1 flex items-center gap-3 bg-midnight-deep hover:bg-slate-850 text-white px-4 py-3 rounded-xl transition-all shadow-md group border border-slate-800"
              >
                {/* Custom Google Play Logo */}
                <svg className="w-6 h-6 text-white group-hover:scale-105 transition-transform shrink-0" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M3,5.27V18.73L16.55,12L3,5.27M17.87,11.33L19.85,12.33C20.25,12.53 20.25,13.1 19.85,13.3L17.87,14.3L15,12.87L17.87,11.33M3,3C3.4,3 3.8,3.13 4.13,3.4L18.8,10.73L14.4,12.93L3,3M3,21L14.4,11.07L18.8,13.27L4.13,20.6C3.8,20.87 3.4,21 3,21Z" />
                </svg>
                <div className="text-left">
                  <p className="text-[9px] text-slate-400 uppercase font-bold leading-none">GET IT ON</p>
                  <p className="text-xs font-bold text-white font-sans leading-tight">Google Play</p>
                </div>
              </a>
            </div>

            <div className="text-[10px] text-slate-400 font-semibold text-center mt-1">
              *App service runs globally in partnership with METRAVON network routing.
            </div>
          </div>
        </div>

        {/* Right Column - Beautiful Rate Calculator Widget */}
        <div className="lg:col-span-7">
          <div className="bg-white rounded-[2rem] border border-slate-200/95 shadow-2xl shadow-brand-primary/5 p-6 md:p-8">
            
            {/* Widget Title & Mode selector */}
            <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
              <div>
                <h3 className="text-xl font-bold text-midnight-deep">USD Rate Calculator</h3>
                <p className="text-xs font-semibold text-slate-400 leading-none pt-1">Real-time valuation based on CBN parameters</p>
              </div>

              {/* Toggle switch */}
              <div className="flex p-1 bg-slate-100 rounded-xl border border-slate-200 shrink-0">
                <button 
                  onClick={() => setCalcMode('fund')}
                  className={`px-4 py-2 rounded-lg text-xs font-bold transition-all ${
                    calcMode === 'fund'
                      ? 'bg-brand-primary text-white shadow-sm'
                      : 'text-slate-500 hover:text-slate-800'
                  }`}
                >
                  Fund Card
                </button>
                <button 
                  onClick={() => setCalcMode('withdraw')}
                  className={`px-2.5 sm:px-4 py-2 rounded-lg text-xs font-bold transition-all ${
                    calcMode === 'withdraw'
                      ? 'bg-brand-primary text-white shadow-sm'
                      : 'text-slate-500 hover:text-slate-800'
                  }`}
                >
                  Withdraw
                </button>
              </div>
            </div>

            {/* Input boxes */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-8">
              
              <div className="space-y-2">
                <label className="text-xs font-bold text-slate-500">USD Amount (Input)</label>
                <div className="relative">
                  <span className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-600 font-bold">$</span>
                  <input 
                    type="number"
                    value={usdAmount}
                    onChange={(e) => setUsdAmount(Math.max(0, parseFloat(e.target.value) || 0))}
                    className="w-full pl-8 pr-4 py-4 rounded-xl border border-slate-200 font-bold text-midnight-deep text-lg bg-slate-50 outline-none focus:border-brand-primary focus:bg-white focus:ring-1 focus:ring-brand-primary/10 transition-all"
                  />
                </div>
              </div>

              <div className="space-y-2">
                <label className="text-xs font-bold text-slate-500">NGN Equivalent Wallet balance</label>
                <div className="relative">
                  <span className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-600 font-bold">₦</span>
                  <input 
                    type="number"
                    value={ngnAmount}
                    onChange={(e) => handleNgnChange(parseInt(e.target.value) || 0)}
                    className="w-full pl-8 pr-4 py-4 rounded-xl border border-slate-200 font-bold text-midnight-deep text-lg bg-slate-50 outline-none focus:border-brand-primary focus:bg-white focus:ring-1 focus:ring-brand-primary/10 transition-all"
                  />
                </div>
              </div>

            </div>

            {/* Calculations breakout panel */}
            <div className="bg-brand-primary/5 rounded-2xl p-6 border border-brand-primary/10 space-y-4">
              <div className="flex justify-between items-center text-xs">
                <span className="text-slate-500 font-semibold flex items-center gap-1.5">
                  <TrendingUp className="w-4 h-4 text-brand-electric" /> Rates Applied
                </span>
                <span className="font-bold text-midnight-deep">
                  ₦{calcMode === 'fund' ? fundingRate : withdrawRate} per $1 USD
                </span>
              </div>

              <div className="h-px bg-slate-200/65"></div>

              <div className="space-y-1.5 text-xs text-slate-600">
                <p>
                  {calcMode === 'fund' ? 'Funding' : 'Withdrawing'} <span className="font-bold text-midnight-deep">{formattedCurrency(usdAmount, 'USD')}</span> {calcMode === 'fund' ? 'costs' : 'yields'} <span className="font-bold text-midnight-deep">{formattedCurrency(ngnAmount, 'NGN')}</span> in your pocket.
                </p>
                {calcMode === 'fund' && (
                  <p className="text-[11px] text-slate-400">
                    *Includes automatic card deposit parameters. Transparent exchange indexing with no hidden margins.
                  </p>
                )}
                <p className="text-[11px] text-brand-primary/80 font-semibold pt-1">
                  Card setup fee today: <span className="font-bold">${cardSetupFee.toFixed(2)} USD</span> (inclusive of {formattedCurrency(freeStartingBalance, 'USD')} preloaded starting balance to spend instantly on activation).
                </p>
              </div>
            </div>

            {/* Pricing Rates Grid overview */}
            <div className="grid grid-cols-3 gap-3 sm:gap-4 mt-8">
              
              <div className="p-4 rounded-xl bg-slate-50 border border-slate-100 text-center space-y-1">
                <p className="text-[9px] font-extrabold uppercase tracking-widest text-slate-400">Fund Card</p>
                <p className="text-md sm:text-lg font-black text-midnight-deep">₦1,393</p>
                <p className="text-[8px] sm:text-[9px] font-semibold text-slate-400">per USD funded</p>
              </div>

              <div className="p-4 rounded-xl bg-slate-50 border border-slate-100 text-center space-y-1">
                <p className="text-[9px] font-extrabold uppercase tracking-widest text-slate-400">Withdrawal</p>
                <p className="text-md sm:text-lg font-black text-midnight-deep">₦1,348</p>
                <p className="text-[8px] sm:text-[9px] font-semibold text-slate-400">to NGN wallet</p>
              </div>

              <div className="p-4 rounded-xl bg-slate-50 border border-slate-100 text-center space-y-1">
                <p className="text-[9px] font-extrabold uppercase tracking-widest text-slate-400">SETUP COST</p>
                <p className="text-md sm:text-lg font-black text-midnight-deep">$7.50</p>
                <p className="text-[8px] sm:text-[9px] font-semibold text-slate-400">includes $5 load</p>
              </div>

            </div>

          </div>
        </div>

      </div>
    </section>
  );
}
