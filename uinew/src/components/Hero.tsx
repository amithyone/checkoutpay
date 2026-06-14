import { useState, useEffect } from "react";
import { 
  ArrowRight, 
  ShieldAlert, 
  ShieldCheck, 
  Sparkles, 
  Smartphone, 
  CreditCard, 
  Globe, 
  Copy, 
  Check, 
  Play, 
  RotateCcw,
  RefreshCw,
  Send,
  MessageSquare
} from "lucide-react";
import { motion, AnimatePresence } from "motion/react";

interface HeroProps {
  onOpenAuth: (mode: 'login' | 'register') => void;
  showDemoInitially?: boolean;
}

export default function Hero({ onOpenAuth, showDemoInitially = false }: HeroProps) {
  const [activeTab, setActiveTab] = useState<'visual' | 'playground'>('visual');
  const [copiedText, setCopiedText] = useState(false);
  
  // Playground states
  const [payMethod, setPayMethod] = useState<'bank' | 'card' | 'whatsapp'>('bank');
  const [whatsappCode, setWhatsappCode] = useState<string>('CPAY-10000-7492');
  const [payAmount, setPayAmount] = useState<number>(10000);
  const [paymentStep, setPaymentStep] = useState<'input' | 'processing' | 'success'>('input');
  const [simulatedAccount, setSimulatedAccount] = useState({
    bank: 'Providus Bank',
    accountNumber: '9520148392',
    accountName: 'CheckoutPay - METRAVON LTD'
  });

  useEffect(() => {
    setWhatsappCode(`CPAY-${payAmount}-${Math.floor(1000 + Math.random() * 9000)}`);
  }, [payAmount]);

  useEffect(() => {
    if (showDemoInitially) {
      setActiveTab('playground');
      const element = document.getElementById('hero-section');
      if (element) {
        element.scrollIntoView({ behavior: 'smooth' });
      }
    }
  }, [showDemoInitially]);

  const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text);
    setCopiedText(true);
    setTimeout(() => setCopiedText(false), 2000);
  };

  const handleStartPayment = () => {
    setPaymentStep('processing');
    setTimeout(() => {
      setPaymentStep('success');
    }, 2200);
  };

  const handleResetPayment = () => {
    setPaymentStep('input');
  };

  // Generate a random dynamic account number
  const regenerateAccount = () => {
    const banks = ['Providus Bank', 'Wema Bank', 'Sterling Bank', 'Titan Trust Bank'];
    const randomBank = banks[Math.floor(Math.random() * banks.length)];
    const num = Math.floor(1000000000 + Math.random() * 9000000000).toString();
    setSimulatedAccount({
      bank: randomBank,
      accountNumber: num,
      accountName: 'CheckoutPay - METRAVON LTD'
    });
  };

  const formattedAmount = (val: number) => {
    return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 0 }).format(val);
  };

  return (
    <section id="hero-section" className="pt-32 pb-24 px-6 md:px-12 max-w-7xl mx-auto overflow-hidden">
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
        
        {/* Left copy */}
        <div className="lg:col-span-6 space-y-8">
          <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-brand-primary/10 border border-brand-primary/25 text-brand-primary font-semibold text-xs">
            <span className="mr-1 h-2.5 w-2.5 rounded-full bg-brand-primary animate-pulse"></span> 
            Built to stay out of the way.
          </div>
          
          <h1 className="text-4xl md:text-5xl lg:text-6xl font-black text-midnight-deep tracking-tight leading-tight">
            Intelligent <span className="text-brand-primary relative">Payment Gateway <span className="absolute bottom-1.5 left-0 w-full h-2 bg-brand-electric/15 -z-10 rounded"></span></span> for Nigerian Business
          </h1>
          
          <p className="text-base md:text-lg text-slate-600 leading-relaxed font-medium">
            Built to accept and process payments in Nigeria. Nothing more. <strong className="text-midnight-deep font-bold">1% + ₦50</strong> per transaction. The work should speak for itself.
          </p>

          <div className="flex flex-wrap gap-4 pt-4">
            <button 
              onClick={() => onOpenAuth('register')}
              className="bg-brand-primary text-white font-semibold text-sm px-8 py-4 rounded-xl flex items-center gap-2 shadow-lg shadow-brand-primary/20 hover:bg-brand-secondary active:scale-98 hover:translate-y-[-2px] transition-all"
            >
              Get started <ArrowRight className="w-4 h-4" />
            </button>
            <a 
              href="#pricing"
              className="px-8 py-4 rounded-xl font-semibold text-sm text-midnight-deep bg-slate-50 border border-slate-200/80 hover:bg-slate-100/75 transition-all text-center"
            >
              View pricing
            </a>
          </div>

          <div className="pt-6 flex flex-wrap items-center gap-x-6 gap-y-3 text-slate-500 text-xs font-semibold">
            <span className="flex items-center gap-1.5 bg-slate-100 py-1.5 px-3 rounded-lg border border-slate-200/50">
              <ShieldCheck className="w-4 h-4 text-brand-electric" /> Licensed by CBN
            </span>
            <span className="flex items-center gap-1.5 bg-slate-100 py-1.5 px-3 rounded-lg border border-slate-200/50">
              <Globe className="w-4 h-4 text-brand-primary" /> Powered by METRAVON INNOVATION LTD
            </span>
          </div>
        </div>

        {/* Right Preview Frame with Interactive Toggles */}
        <div className="lg:col-span-6 w-full relative">
          
          {/* Subtle neon accents */}
          <div className="absolute -inset-10 bg-gradient-to-tr from-brand-electric/20 to-emerald-500/10 blur-3xl rounded-full opacity-65 pointer-events-none -z-10"></div>
          
          {/* Tabs */}
          <div className="flex justify-between items-center mb-4 bg-slate-100 p-1 rounded-xl max-w-sm mx-auto border border-slate-200/50">
            <button
              onClick={() => setActiveTab('visual')}
              className={`flex-1 flex items-center justify-center gap-2 py-2.5 rounded-lg text-xs font-bold transition-all ${
                activeTab === 'visual'
                  ? 'bg-white text-midnight-deep shadow-sm'
                  : 'text-slate-500 hover:text-slate-800'
              }`}
            >
              <Smartphone className="w-3.5 h-3.5" />
              Product View
            </button>
            <button
              onClick={() => setActiveTab('playground')}
              className={`flex-1 flex items-center justify-center gap-2 py-2.5 rounded-lg text-xs font-bold transition-all relative ${
                activeTab === 'playground'
                  ? 'bg-white text-midnight-deep shadow-sm'
                  : 'text-slate-500 hover:text-slate-800'
              }`}
            >
              <Sparkles className="w-3.5 h-3.5 text-brand-electric animate-bounce" />
              Live Gateway Playground
              <span className="absolute -top-1 -right-1 flex h-2 w-2">
                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span className="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
              </span>
            </button>
          </div>

          <AnimatePresence mode="wait">
            {activeTab === 'visual' ? (
              /* Aida Product Visual Frame */
              <motion.div
                key="visual"
                initial={{ opacity: 0, y: 15 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, y: -15 }}
                transition={{ duration: 0.3 }}
                className="relative group rounded-3xl overflow-hidden shadow-2xl shadow-brand-primary/10 border border-slate-200"
              >
                <img 
                  src="https://lh3.googleusercontent.com/aida/AP1WRLt1DEV1w8HkN3k9FCQDX6yid4L5cthZ7XQxVmIV0BC7W27EtotnocSzJsVcXzZx4-KTYsasE8kWjpNJu3DdeFA-L1l-A7G1i0PRHxQoP_0liq2LiQ0QCN4JbNL03onWqYftBAW0WRHoEVISje8Ofch18jzvD_jQOpsVRi4bvJHqtL7LoBFHoJ_XPfYkBwp9bwKJbSYZfdKbjUW5y7625DZhaoxAlpyypvk_scws0Dm-QHGAdPu9ut1Nhw" 
                  alt="CheckoutPay Premium Smartphone Screen Success Checkout" 
                  className="w-full h-auto object-cover rounded-3xl"
                />
                
                {/* Float tag */}
                <div className="absolute bottom-6 left-6 right-6 bg-white/95 backdrop-blur-md p-4 rounded-2xl border border-slate-100 flex items-center justify-between shadow-lg">
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-full bg-brand-primary/10 flex items-center justify-center">
                      <ShieldCheck className="w-5 h-5 text-brand-primary" />
                    </div>
                    <div>
                      <p className="font-bold text-xs text-midnight-deep">Instant Settlements</p>
                      <p className="text-[10px] font-semibold text-slate-400">Verified by METRAVON LTD</p>
                    </div>
                  </div>
                  <span className="bg-emerald-500 text-white text-[9px] font-black uppercase px-2.5 py-1 rounded-full tracking-wider">
                    ● SECURE
                  </span>
                </div>
              </motion.div>
            ) : (
              /* Interactive Sandbox Payment Form Widget */
              <motion.div
                key="playground"
                initial={{ opacity: 0, y: 15 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, y: -15 }}
                transition={{ duration: 0.3 }}
                className="bg-white rounded-3xl border border-slate-200/90 shadow-2xl p-6 md:p-8 relative min-h-[500px] flex flex-col justify-between"
              >
                {/* Playground Header */}
                <div>
                  <div className="flex justify-between items-center pb-4 border-b border-slate-100">
                    <div className="flex items-center gap-2">
                      <div className="w-7 h-7 rounded-lg bg-brand-primary flex items-center justify-center">
                        <ShieldCheck className="w-4 h-4 text-white" />
                      </div>
                      <span className="font-bold text-xs text-midnight-deep uppercase tracking-wider">CheckoutPay Sandbox Secure</span>
                    </div>
                    <span className="bg-slate-100 text-slate-500 border border-slate-200 text-[10px] font-bold px-2 py-0.5 rounded-md">
                      Test-mode
                    </span>
                  </div>

                  {paymentStep === 'input' && (
                    <motion.div
                      initial={{ opacity: 0 }}
                      animate={{ opacity: 1 }}
                      className="space-y-6 pt-4"
                    >
                      <div className="space-y-2">
                        <label className="text-xs font-bold text-slate-500 block">Select Demo Pay-in Channel</label>
                        <div className="grid grid-cols-3 gap-2">
                          <button
                            onClick={() => setPayMethod('bank')}
                            className={`p-3 rounded-xl border flex flex-col items-center justify-center gap-1.5 transition-all ${
                              payMethod === 'bank'
                                ? 'border-brand-primary bg-brand-primary/5 text-brand-primary font-bold shadow-sm'
                                : 'border-slate-200 hover:border-slate-300 text-slate-500'
                            }`}
                          >
                            <Smartphone className="w-4 h-4" />
                            <span className="text-[10px]">Bank Transfer</span>
                          </button>
                          <button
                            onClick={() => setPayMethod('card')}
                            className={`p-3 rounded-xl border flex flex-col items-center justify-center gap-1.5 transition-all ${
                              payMethod === 'card'
                                ? 'border-brand-primary bg-brand-primary/5 text-brand-primary font-bold shadow-sm'
                                : 'border-slate-200 hover:border-slate-300 text-slate-500'
                            }`}
                          >
                            <CreditCard className="w-4 h-4" />
                            <span className="text-[10px]">Debit Card</span>
                          </button>
                          <button
                            onClick={() => setPayMethod('whatsapp')}
                            className={`p-3 rounded-xl border flex flex-col items-center justify-center gap-1.5 transition-all ${
                              payMethod === 'whatsapp'
                                ? 'border-brand-primary bg-brand-primary/5 text-brand-primary font-bold shadow-sm'
                                : 'border-slate-200 hover:border-slate-300 text-slate-500'
                            }`}
                          >
                            <MessageSquare className="w-4 h-4 text-emerald-500" />
                            <span className="text-[10px] whitespace-nowrap">Pay via WhatsApp</span>
                          </button>
                        </div>
                      </div>

                      {/* Amount Customizer */}
                      <div className="space-y-2">
                        <div className="flex justify-between items-center">
                          <label className="text-xs font-bold text-slate-500">Transaction Amount (NGN)</label>
                          <span className="text-xs font-bold text-brand-primary">Fee: {formattedAmount(Math.min(payAmount * 0.01 + 50, payAmount * 0.01 + 50))}</span>
                        </div>
                        <div className="relative">
                          <span className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 font-bold">₦</span>
                          <input 
                            type="number"
                            value={payAmount}
                            onChange={(e) => setPayAmount(Math.max(100, parseInt(e.target.value) || 0))}
                            className="w-full bg-slate-50 border border-slate-200 rounded-xl py-3.5 pl-8 pr-4 text-midnight-deep font-bold outline-none focus:border-brand-primary focus:bg-white text-base"
                          />
                        </div>
                        <div className="flex gap-2">
                          {[5000, 10000, 50000, 100000].map((preset) => (
                            <button
                              key={preset}
                              onClick={() => setPayAmount(preset)}
                              className="text-[10px] font-bold px-2.5 py-1 bg-slate-100 text-slate-600 rounded-md hover:bg-slate-200"
                            >
                              +{formattedAmount(preset)}
                            </button>
                          ))}
                        </div>
                      </div>

                      {/* Displaying specifics depending on channel */}
                      <div className="p-4 bg-slate-50 rounded-2xl border border-slate-100 space-y-4">
                        {payMethod === 'bank' && (
                          <div className="space-y-3">
                            <div className="flex justify-between items-center">
                              <span className="text-xs text-slate-400 font-semibold uppercase tracking-wider">Dynamic Sandbox Account</span>
                              <button 
                                onClick={regenerateAccount}
                                className="text-brand-primary hover:text-brand-secondary text-xs font-semibold flex items-center gap-1 active:rotate-180 transition-transform duration-300"
                                title="Regenerate Test Account"
                              >
                                <RefreshCw className="w-3 h-3" /> Roll Alternative
                              </button>
                            </div>
                            <div className="bg-white p-3 rounded-lg border border-slate-200/80 space-y-1">
                              <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Virtual Bank</p>
                              <p className="text-xs font-bold text-midnight-deep">{simulatedAccount.bank}</p>
                              
                              <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400 pt-2">Account Number</p>
                              <div className="flex items-center justify-between">
                                <span className="font-mono text-base font-bold text-brand-primary tracking-wider">{simulatedAccount.accountNumber}</span>
                                <button
                                  onClick={() => copyToClipboard(simulatedAccount.accountNumber)}
                                  className="text-slate-400 hover:text-brand-primary p-1 bg-slate-50 rounded border border-slate-100"
                                  title="Copy Number"
                                >
                                  {copiedText ? <Check className="w-3.5 h-3.5 text-emerald-500" /> : <Copy className="w-3.5 h-3.5" />}
                                </button>
                              </div>

                              <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400 pt-2">Beneficiary</p>
                              <p className="text-xs font-semibold text-slate-600">{simulatedAccount.accountName}</p>
                            </div>
                            <p className="text-[10px] text-slate-400 text-center font-medium">
                              Send funds to this simulated account. Tap "Proceed Payment" to trigger the automatic payment-received callback simulation.
                            </p>
                          </div>
                        )}

                        {payMethod === 'card' && (
                          <div className="space-y-3">
                            <span className="text-xs text-slate-400 font-semibold uppercase tracking-wider">Sandbox Debit Card (Visa/Mastercard)</span>
                            <div className="space-y-2">
                              <input 
                                type="text" 
                                readOnly 
                                value="4532  8901  2345  6789" 
                                className="w-full bg-white border border-slate-200 rounded-lg py-2.5 px-3 text-sm font-mono tracking-widest text-midnight-deep" 
                              />
                              <div className="grid grid-cols-2 gap-2">
                                <input 
                                  type="text" 
                                  readOnly 
                                  value="10/28" 
                                  className="bg-white border border-slate-200 rounded-lg py-2.5 px-3 text-xs font-mono text-center text-midnight-deep" 
                                />
                                <input 
                                  type="text" 
                                  readOnly 
                                  value="321" 
                                  className="bg-white border border-slate-200 rounded-lg py-2.5 px-3 text-xs font-mono text-center text-midnight-deep" 
                                />
                              </div>
                            </div>
                            <p className="text-[11px] text-slate-400 font-medium leading-normal text-center">
                              Card initialized with prefunded Sandbox credit. Simply proceed to simulate 3D-Secure success verification.
                            </p>
                          </div>
                        )}

                        {payMethod === 'whatsapp' && (
                          <div className="space-y-3.5 text-left">
                            <div className="flex justify-between items-center">
                              <span className="text-xs text-slate-400 font-semibold uppercase tracking-wider">Pay via Verified WhatsApp</span>
                              <span className="text-[10px] font-black text-emerald-600 bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-100 flex items-center gap-1">
                                <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Verified Bot
                              </span>
                            </div>
                            
                            <div className="bg-white p-3 rounded-xl border border-slate-200/80 space-y-2.5">
                              <div className="space-y-1">
                                <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Step 1: Copy Your Special Code</p>
                                <div className="flex items-center justify-between bg-slate-50 border border-slate-200 p-2 rounded-lg">
                                  <span className="font-mono text-xs font-black text-emerald-600 tracking-wider bg-emerald-50/50 px-2 py-0.5 rounded border border-emerald-100/50">{whatsappCode}</span>
                                  <button
                                    onClick={() => copyToClipboard(whatsappCode)}
                                    className="text-slate-400 hover:text-emerald-600 p-1 bg-white rounded border border-slate-150 transition-colors"
                                    title="Copy Code"
                                  >
                                    {copiedText ? <Check className="w-3.5 h-3.5 text-emerald-500" /> : <Copy className="w-3.5 h-3.5" />}
                                  </button>
                                </div>
                              </div>

                              <div className="space-y-1 pt-0.5">
                                <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Step 2: Send to Verified bot</p>
                                <div className="flex items-center justify-between text-xs font-bold text-slate-700 bg-slate-50 p-2 rounded-lg border border-slate-200">
                                  <div className="flex items-center gap-1.5">
                                    <span className="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                                    <span>+234 1 440 2201 <span className="text-[9px] text-slate-400 font-medium">(CheckoutPay)</span></span>
                                  </div>
                                </div>
                              </div>
                            </div>

                            {/* Live WhatsApp chat simulator inside the playground box */}
                            <div className="bg-emerald-50/40 border border-emerald-100/60 rounded-xl p-3 space-y-2.5 text-xs">
                              <p className="text-[9px] text-slate-400 font-bold uppercase tracking-wider text-center">Interactive Sandbox WhatsApp Chat Simulator</p>
                              
                              <div className="space-y-2">
                                <div className="flex justify-end">
                                  <div className="bg-emerald-500 text-white p-2 rounded-lg rounded-tr-none max-w-[85%] shadow-sm">
                                    <p className="font-mono text-xs font-bold">{whatsappCode}</p>
                                    <span className="text-[8px] text-emerald-100 block text-right mt-0.5">Sent ✓✓</span>
                                  </div>
                                </div>
                                
                                <div className="flex justify-start">
                                  <div className="bg-white text-slate-800 p-2.5 rounded-lg rounded-tl-none max-w-[85%] border border-emerald-100 shadow-sm space-y-1">
                                    <p className="font-semibold text-[11px] text-emerald-600">CheckoutPay Bot</p>
                                    <p className="text-[10.5px] leading-tight font-medium">
                                      ✅ Special payment code matching transaction of <strong className="text-slate-900">{formattedAmount(payAmount)}</strong> validated!
                                    </p>
                                    <span className="text-[8.5px] text-slate-400 block mt-0.5">Verified checkout channel</span>
                                  </div>
                                </div>
                              </div>
                            </div>
                            
                            <p className="text-[10px] text-slate-400 text-center font-semibold leading-relaxed">
                              Send this exact code keyword to the interactive assistant. To automate testing this flow, click the "Proceed Test Payment" trigger below to fire standard webhook signals.
                            </p>
                          </div>
                        )}
                      </div>
                    </motion.div>
                  )}

                  {paymentStep === 'processing' && (
                    <motion.div
                      initial={{ opacity: 0, scale: 0.95 }}
                      animate={{ opacity: 1, scale: 1 }}
                      className="flex flex-col items-center justify-center py-16 space-y-4"
                    >
                      <div className="w-16 h-16 rounded-full border-4 border-slate-100 border-t-brand-primary animate-spin"></div>
                      <div className="text-center space-y-1">
                        <p className="font-bold text-sm text-midnight-deep">Connecting Gateway Processor</p>
                        <p className="text-xs font-semibold text-slate-400 flex items-center justify-center gap-1">
                          <span className="inline-block w-2 h-2 bg-emerald-500 rounded-full animate-ping"></span> 
                          Awaiting webhook validation ...
                        </p>
                      </div>
                      <div className="bg-slate-50 border border-slate-100 rounded-xl p-3 text-[11px] font-mono text-slate-500 text-left w-full max-w-xs">
                        <p className="text-slate-400">// CheckoutPay System Handshake</p>
                        <p>POST /api/v1/checkout/resolve</p>
                        <p>Channel: {payMethod.toUpperCase()}</p>
                        <p>Issuer: METRAVON NETWORK</p>
                        <p>Payload: amount={payAmount} status=PENDING</p>
                      </div>
                    </motion.div>
                  )}

                  {paymentStep === 'success' && (
                    <motion.div
                      initial={{ opacity: 0, scale: 0.95 }}
                      animate={{ opacity: 1, scale: 1 }}
                      className="flex flex-col items-center justify-center py-12 space-y-4"
                    >
                      <div className="w-16 h-16 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-500 animate-bounce">
                        <Check className="w-8 h-8 stroke-[3]" />
                      </div>
                      <div className="text-center space-y-1">
                        <h4 className="font-extrabold text-xl text-emerald-600">Payment Successful!</h4>
                        <p className="text-sm font-semibold text-slate-400">Merchant notified via checkout hook.</p>
                      </div>

                      <div className="p-4 bg-emerald-50/50 rounded-2xl border border-emerald-100 w-full text-center max-w-xs">
                        <p className="text-xs font-semibold text-slate-500">Transferred Value</p>
                        <p className="text-2xl font-black text-midnight-deep">{formattedAmount(payAmount)}</p>
                        <div className="flex justify-between text-[11px] font-semibold text-slate-400 mt-3 pt-2 border-t border-emerald-100/50">
                          <span>Gateway Fee:</span>
                          <span>{formattedAmount(payAmount * 0.01 + 50)}</span>
                        </div>
                        <div className="flex justify-between text-[11px] font-semibold text-slate-400 mt-1">
                          <span>Pay Settlement:</span>
                          <span>24Hrs Merchant Bank</span>
                        </div>
                      </div>

                      <div className="bg-slate-900 text-slate-350 rounded-xl p-3 text-[10px] font-mono text-left w-full max-w-xs space-y-1 overflow-x-auto max-h-[120px]">
                        <p className="text-emerald-400">// API Webhook Payload Received</p>
                        <p className="text-slate-400">{`{`}</p>
                        <p className="pl-4">"status": "success",</p>
                        <p className="pl-4">"transaction_id": "TXN_MTR83${Math.floor(Math.random() * 90000)}",</p>
                        <p className="pl-4">"amount_cents": {payAmount * 100},</p>
                        <p className="pl-4">"partner": "METRAVON_INNOVATION"</p>
                        <p className="text-slate-400">{`}`}</p>
                      </div>
                    </motion.div>
                  )}
                </div>

                {/* Playground Action Bar */}
                <div className="pt-6 border-t border-slate-100 flex items-center justify-between h-16 bg-white shrink-0">
                  {paymentStep === 'input' ? (
                    <button
                      onClick={handleStartPayment}
                      className="w-full bg-brand-primary text-white py-3.5 rounded-xl font-bold text-sm tracking-wide hover:bg-brand-secondary transition-all shadow-md shadow-brand-primary/10 flex items-center justify-center gap-2"
                    >
                      <Play className="w-4 h-4 fill-white" /> Proceed Test Payment ({formattedAmount(payAmount)})
                    </button>
                  ) : paymentStep === 'processing' ? (
                    <button
                      disabled
                      className="w-full bg-slate-100 text-slate-400 py-3.5 rounded-xl font-bold text-sm flex items-center justify-center gap-2 cursor-not-allowed"
                    >
                      <RefreshCw className="w-4 h-4 animate-spin text-slate-400" /> Resolving Callback...
                    </button>
                  ) : (
                    <button
                      onClick={handleResetPayment}
                      className="w-full bg-slate-900 text-white hover:bg-slate-800 py-3.5 rounded-xl font-bold text-sm transition-all flex items-center justify-center gap-2"
                    >
                      <RotateCcw className="w-4 h-4" /> Try with Another Amount
                    </button>
                  )}
                </div>
              </motion.div>
            )}
          </AnimatePresence>
        </div>
      </div>
    </section>
  );
}
