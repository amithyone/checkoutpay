import { useState } from "react";
import { 
  FileText, 
  Ticket, 
  Users, 
  Home, 
  ArrowUpRight, 
  ShieldCheck, 
  Check, 
  Zap, 
  X,
  Plus,
  Send,
  Sparkles,
  Layers
} from "lucide-react";
import { motion, AnimatePresence } from "motion/react";

export default function CommerceInfrastructure() {
  const [activePeek, setActivePeek] = useState<string | null>(null);

  // Invoice demo internal states
  const [invoiceClient, setInvoiceClient] = useState("Amadi Corp");
  const [invoiceAmount, setInvoiceAmount] = useState(75000);
  const [invoiceSent, setInvoiceSent] = useState(false);

  // Batch Payout internal states
  const [staffCount, setStaffCount] = useState(8);
  const [staffSalary, setStaffSalary] = useState(150000);
  const [payoutProcessing, setPayoutProcessing] = useState(false);
  const [payoutFinished, setPayoutFinished] = useState(false);

  const handleSendInvoice = () => {
    setInvoiceSent(true);
    setTimeout(() => {
      setInvoiceSent(false);
      setActivePeek(null);
    }, 2800);
  };

  const handleTriggerPayout = () => {
    setPayoutProcessing(true);
    setPayoutFinished(false);
    setTimeout(() => {
      setPayoutProcessing(false);
      setPayoutFinished(true);
    }, 2200);
  };

  const resetPayout = () => {
    setPayoutFinished(false);
    setPayoutProcessing(false);
  };

  const formattedMoney = (val: number) => {
    return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 0 }).format(val);
  };

  return (
    <section id="features" className="py-24 bg-white">
      <div className="px-6 md:px-12 max-w-7xl mx-auto">
        
        {/* Section Header */}
        <div className="text-center max-w-2xl mx-auto mb-16 space-y-4">
          <h2 className="text-3xl md:text-5xl font-black text-midnight-deep tracking-tight">
            Unified Commerce Infrastructure
          </h2>
          <p className="text-sm md:text-base font-semibold text-slate-500 leading-relaxed">
            One platform to manage your entire financial ecosystem. From checkout APIs to specialized collection channels.
          </p>
        </div>

        {/* 3x2 Bento Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          
          {/* Card 1: Online Payments */}
          <div className="group bg-slate-50 border border-slate-200/60 rounded-3xl p-6 flex flex-col justify-between hover:shadow-xl hover:shadow-brand-primary/5 transition-all duration-300">
            <div className="space-y-4">
              <div className="h-48 rounded-xl overflow-hidden relative border border-slate-200/50">
                <img 
                  alt="Online Payments Screen success" 
                  className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" 
                  src="https://lh3.googleusercontent.com/aida/AP1WRLt1DEV1w8HkN3k9FCQDX6yid4L5cthZ7XQxVmIV0BC7W27EtotnocSzJsVcXzZx4-KTYsasE8kWjpNJu3DdeFA-L1l-A7G1i0PRHxQoP_0liq2LiQ0QCN4JbNL03onWqYftBAW0WRHoEVISje8Ofch18jzvD_jQOpsVRi4bvJHqtL7LoBFHoJ_XPfYkBwp9bwKJbSYZfdKbjUW5y7625DZhaoxAlpyypvk_scws0Dm-QHGAdPu9ut1Nhw"
                />
                <div className="absolute top-4 left-4 bg-white/90 backdrop-blur shadow p-2 rounded-lg">
                  <span className="text-[10px] font-black uppercase text-brand-primary">Gateway API</span>
                </div>
              </div>
              <h3 className="text-xl font-bold text-midnight-deep leading-tight">Online Payments</h3>
              <p className="text-xs font-semibold text-slate-500 leading-relaxed">
                Receive NGN payments through bank transfers with reliable automated matching and instant virtual account numbers.
              </p>
            </div>
            <div className="mt-6 pt-4 border-t border-slate-200/50 flex flex-wrap gap-x-4 gap-y-2 text-[10px] font-extrabold text-brand-electric">
              <span className="flex items-center gap-1"><Zap className="w-3.5 h-3.5" /> Instant Matching</span>
              <span className="flex items-center gap-1"><Check className="w-3.5 h-3.5" /> Secure Hooking</span>
            </div>
          </div>

          {/* Card 2: Smart Invoices */}
          <div className="group bg-slate-50 border border-slate-200/60 rounded-3xl p-6 flex flex-col justify-between hover:shadow-xl hover:shadow-brand-primary/5 transition-all duration-300">
            <div className="space-y-4">
              <div className="h-48 rounded-xl overflow-hidden relative border border-slate-200/50">
                <img 
                  alt="Smart Invoices Office laptop" 
                  className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" 
                  src="https://lh3.googleusercontent.com/aida/AP1WRLvLOXj5RAYne3JGzmoahFQoYy7JF0lB3HZnZ3yNT-l3Ayk2RWIfzQV1J9PM_2cBqy-IA-WzrTYdyv_4bTzicyT2MI2ezi34FNKG0ciyOlOk7nh8X0x_7brDLD9zrgQmA32qg-fUaOJS0z_5Nkg8x60TVjcKszoxtDhi-7XH_x_dwIFLQPmtO8KKqNL_v8IfiqkRleZWg7E81ytdCH-7HoKcxQUIddGRjTXEptr6p-sF4ar-iV1rUtOKH0M"
                />
                <div className="absolute top-4 left-4 bg-white/90 backdrop-blur shadow p-2 rounded-lg">
                  <span className="text-[10px] font-black uppercase text-brand-primary flex items-center gap-1">
                    <FileText className="w-3 h-3" /> Billing
                  </span>
                </div>
              </div>
              <h3 className="text-xl font-bold text-midnight-deep leading-tight">Smart Invoices</h3>
              <p className="text-xs font-semibold text-slate-500 leading-relaxed">
                Generate and send professional VAT invoices to contract customers in seconds. Track receipt of NGN instantly in real-time.
              </p>
            </div>
            <button 
              onClick={() => setActivePeek('invoice')}
              className="mt-6 w-full py-2.5 bg-white border border-slate-200 hover:border-brand-primary text-slate-700 hover:text-brand-primary text-xs font-bold rounded-xl transition-all flex items-center justify-center gap-1"
            >
              Simulate Invoicing <ArrowUpRight className="w-3.5 h-3.5" />
            </button>
          </div>

          {/* Card 3: Event Tickets */}
          <div className="group bg-slate-50 border border-slate-200/60 rounded-3xl p-6 flex flex-col justify-between hover:shadow-xl hover:shadow-brand-primary/5 transition-all duration-300">
            <div className="space-y-4">
              <div className="h-48 rounded-xl overflow-hidden relative border border-slate-200/50">
                <img 
                  alt="Event Ticket QR codes scanner" 
                  className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" 
                  src="https://lh3.googleusercontent.com/aida/AP1WRLvp1I9XNQG35CXDfRzUvO_Zy5dp5uP7jbiO5kRHJir16TF9cvpDIrSLJmR51Jm0z09yQB5L6l1MRlJDvSRo49YKDFO7a-2nBEMbvSlZTXabdbP3c8yJMASqa_Qd5NGDVuPGid-s6tMPfykOnkVVOo5AjRR0sYRm_pGQ3kQhipEr9mABWV_9CtGudnOc4ZBTGr89yOmg-baWaDb7f0Z4fOO7_YIBwW9cU97BRmk0Y9v6KjCz5aTBc563f6s"
                />
                <div className="absolute top-4 left-4 bg-white/90 backdrop-blur shadow p-2 rounded-lg">
                  <span className="text-[10px] font-black uppercase text-brand-primary flex items-center gap-1">
                    <Ticket className="w-3 h-3" /> ticketing
                  </span>
                </div>
              </div>
              <h3 className="text-xl font-bold text-midnight-deep leading-tight">Event Tickets</h3>
              <p className="text-xs font-semibold text-slate-500 leading-relaxed">
                Publish high-conversion event checkout pages with built-in QR generation and real-time gate validation options.
              </p>
            </div>
            <button 
              onClick={() => setActivePeek('ticket')}
              className="mt-6 w-full py-2.5 bg-white border border-slate-200 hover:border-brand-primary text-slate-700 hover:text-brand-primary text-xs font-bold rounded-xl transition-all flex items-center justify-center gap-1"
            >
              Preview Event Ticket <ArrowUpRight className="w-3.5 h-3.5" />
            </button>
          </div>

          {/* Card 4: Rentals */}
          <div className="group relative overflow-hidden rounded-3xl h-80 border border-slate-200/50 shadow-sm flex flex-col justify-end p-6">
            <img 
              alt="Rentals Hand key holder" 
              className="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-[700ms]" 
              src="https://lh3.googleusercontent.com/aida/AP1WRLupUCwDP74QdzHtMw8WOZMnvkYdegF8U9SwzcIkvt_K1bRdbk9doD7rK1nYoNLVoZiBr_XBGRvVIht7PavljZUuQXpy-9XTtUr5W7doKhJRqFVcV9UcdqHfpbRN6ZOeMnmvpO9RWrAeVy7-jOtaMIAvSEgaSXJHLKHt2IeSjdsdVVGscHCpR7-BcC2LQUskM38rEciRfQiLObA4Z1u3Oxp3f_uJonv7CKRjF1m4y_QkX57pYi1fTUtgTA"
            />
            <div className="absolute inset-0 bg-gradient-to-t from-midnight-deep/95 via-midnight-deep/45 to-transparent z-10"></div>
            
            <div className="relative z-20 space-y-2">
              <span className="text-[9px] font-extrabold uppercase bg-brand-electric/20 text-brand-electric border border-brand-electric/30 px-2 py-0.5 rounded-full inline-block">
                Property Pay
              </span>
              <h3 className="text-xl font-bold text-white">Rentals</h3>
              <p className="text-xs font-medium text-slate-300">
                Handle rental payments, security deposits, and recurrent landlord settlements with secure escrow features.
              </p>
            </div>
          </div>

          {/* Card 5: Memberships */}
          <div className="bg-slate-50 border border-slate-200/60 rounded-3xl p-6 flex flex-col justify-between hover:shadow-xl hover:shadow-brand-primary/5 transition-all duration-300">
            <div className="space-y-4">
              <div className="w-12 h-12 rounded-xl bg-brand-primary/10 flex items-center justify-center text-brand-primary">
                <Users className="w-6 h-6" />
              </div>
              <h3 className="text-xl font-bold text-midnight-deep">Memberships</h3>
              <p className="text-xs font-semibold text-slate-500 leading-relaxed">
                Automate recurring subscription billings for journals, associations, content sites, and premium clubs with easy automated NGN retry logs.
              </p>
            </div>
            <div className="bg-white px-4 py-3 rounded-2xl border border-slate-200/50 flex justify-between items-center text-xs font-bold text-slate-700">
              <span>Automatic Renewals</span>
              <span className="text-brand-electric">Active Integration</span>
            </div>
          </div>

          {/* Card 6: Payouts & Collections */}
          <div className="bg-midnight-deep text-white border border-slate-800 rounded-3xl p-6 flex flex-col justify-between hover:shadow-2xl transition-all duration-300">
            <div className="space-y-4">
              <div className="w-12 h-12 rounded-xl bg-white/10 flex items-center justify-center text-white border border-white/10">
                <Layers className="w-6 h-6" />
              </div>
              <h3 className="text-xl font-bold">Payouts &amp; Collections</h3>
              <p className="text-xs font-semibold text-slate-400 leading-relaxed">
                Enterprise disbursement utilities. Trigger custom mass batch payouts (salaries, commissions, vendor fees) instantly to any bank.
              </p>
            </div>
            
            <div className="space-y-3 pt-4">
              <button
                onClick={() => setActivePeek('payout')}
                className="w-full py-2.5 bg-brand-primary hover:bg-brand-secondary text-white text-xs font-bold rounded-xl transition-all flex items-center justify-center gap-1.5 shadow"
              >
                Run Batch Payroll Simulator <ArrowUpRight className="w-3.5 h-3.5" />
              </button>
              
              <div className="flex items-center gap-2 text-[9px] font-extrabold uppercase tracking-widest text-[#22C55E]">
                <ShieldCheck className="w-4 h-4 text-[#22C55E]" /> PCI-DSS CERTIFIED SYSTEM
              </div>
            </div>
          </div>

        </div>

        {/* Feature Peeks / Interactive Sandbox Modals */}
        <AnimatePresence>
          {activePeek && (
            <motion.div 
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4"
              onClick={() => setActivePeek(null)}
            >
              <motion.div 
                initial={{ scale: 0.95, y: 15 }}
                animate={{ scale: 1, y: 0 }}
                exit={{ scale: 0.95, y: 15 }}
                className="bg-white overflow-hidden rounded-3xl max-w-md w-full shadow-2xl relative border border-slate-100"
                onClick={(e) => e.stopPropagation()}
              >
                
                {/* Header */}
                <div className="bg-slate-50 px-6 py-4 flex justify-between items-center border-b border-slate-100">
                  <div className="flex items-center gap-2">
                    <Sparkles className="w-4 h-4 text-brand-electric animate-pulse" />
                    <span className="text-xs font-black uppercase text-midnight-deep py-0.5">
                      Sandbox Interactive peek
                    </span>
                  </div>
                  <button 
                    onClick={() => setActivePeek(null)}
                    className="p-1 rounded-lg bg-slate-200/50 hover:bg-slate-200 text-slate-500"
                  >
                    <X className="w-4 h-4" />
                  </button>
                </div>

                {/* Body Details */}
                <div className="p-6">
                  
                  {activePeek === 'invoice' && (
                    <div className="space-y-4">
                      <h4 className="text-base font-bold text-midnight-deep">Smart Invoice Generator</h4>
                      <p className="text-xs font-semibold text-slate-500 leading-normal">
                        Verify how instantly an email is issued. Input client parameters to see dynamic CheckoutPay layouts.
                      </p>

                      <div className="space-y-2.5">
                        <div>
                          <label className="text-[10px] font-bold uppercase text-slate-400">Client Organization</label>
                          <input 
                            type="text" 
                            value={invoiceClient}
                            onChange={(e) => setInvoiceClient(e.target.value)}
                            className="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-xs font-semibold text-midnight-deep outline-none focus:border-brand-primary"
                          />
                        </div>
                        <div>
                          <label className="text-[10px] font-bold uppercase text-slate-400">Invoiced Amount (NGN)</label>
                          <input 
                            type="number" 
                            value={invoiceAmount}
                            onChange={(e) => setInvoiceAmount(parseInt(e.target.value) || 0)}
                            className="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-xs font-semibold text-midnight-deep outline-none focus:border-brand-primary"
                          />
                        </div>
                      </div>

                      <div className="bg-slate-50 p-4 rounded-xl border border-dashed border-slate-200 space-y-2">
                        <div className="flex justify-between text-xs">
                          <span className="text-slate-400">Recipient:</span>
                          <span className="font-bold text-midnight-deep">{invoiceClient}</span>
                        </div>
                        <div className="flex justify-between text-xs">
                          <span className="text-slate-400">Calculated VAT (7.5%):</span>
                          <span className="font-bold text-midnight-deep">{formattedMoney(invoiceAmount * 0.075)}</span>
                        </div>
                        <div className="flex justify-between text-xs pt-1 border-t border-slate-200 font-bold">
                          <span className="text-slate-700">Invoice Total:</span>
                          <span className="text-brand-primary">{formattedMoney(invoiceAmount * 1.075)}</span>
                        </div>
                      </div>

                      {invoiceSent ? (
                        <div className="bg-emerald-50 text-emerald-700 p-3 rounded-lg text-center text-xs font-bold border border-emerald-200 flex items-center justify-center gap-2">
                          <Check className="w-4 h-4 text-emerald-600" /> Digital Invoice dispatched to {invoiceClient}!
                        </div>
                      ) : (
                        <button
                          onClick={handleSendInvoice}
                          className="w-full bg-brand-primary text-white hover:bg-brand-secondary py-3 text-xs font-bold rounded-xl transition-all"
                        >
                          Send Simulated Invoice
                        </button>
                      )}
                    </div>
                  )}

                  {activePeek === 'ticket' && (
                    <div className="space-y-4 flex flex-col items-center">
                      <div className="text-center">
                        <h4 className="text-base font-bold text-midnight-deep">CheckoutPay Generated QR Ticket</h4>
                        <p className="text-xs font-semibold text-slate-400 leading-normal">
                          This is an authentic QR validation sample generated for event entrance.
                        </p>
                      </div>

                      <div className="p-4 bg-slate-100 rounded-2xl border border-slate-200 flex flex-col items-center relative gap-2">
                        {/* Visa card image or SVG QR */}
                        <div className="w-32 h-32 bg-slate-900 rounded-xl flex items-center justify-center text-white border border-slate-800">
                          {/* Simulated QR block layout */}
                          <div className="grid grid-cols-4 gap-1 p-2 bg-white rounded-lg">
                            {[1,0,1,1,0,1,0,0,1,1,1,0,1,0,1,1].map((n, i) => (
                              <div key={i} className={`w-5 h-5 ${n ? 'bg-black' : 'bg-white'}`}></div>
                            ))}
                          </div>
                        </div>

                        <div className="text-center">
                          <p className="text-xs font-black text-midnight-deep">E-TICKET IND-80241</p>
                          <p className="text-[10px] text-slate-500">Lagos Tech Expo 2026</p>
                        </div>
                        <span className="bg-emerald-500 text-white text-[8px] font-extrabold px-2 py-0.5 rounded-full absolute top-2 right-2">
                          VALIDATED
                        </span>
                      </div>

                      <div className="p-3 bg-brand-primary/5 rounded-xl text-center text-[10px] font-semibold text-slate-500 leading-relaxed">
                        Scanning the checkout ticket triggers automated entrance logging inside the Merchant console panel instantly.
                      </div>

                      <button
                        onClick={() => {
                          alert("Beta scanning is active for verified app merchants. Log into the CheckoutPay dashboard on your smartphone.");
                          setActivePeek(null);
                        }}
                        className="w-full bg-midnight-deep text-white hover:bg-slate-850 py-3 text-xs font-bold rounded-xl transition-all"
                      >
                        Authorize Beta Scan
                      </button>
                    </div>
                  )}

                  {activePeek === 'payout' && (
                    <div className="space-y-4">
                      <h4 className="text-base font-bold text-midnight-deep font-sans">Mass Payout Simulator</h4>
                      <p className="text-xs font-semibold text-slate-500 leading-normal">
                        Experience bulk routing. Distribute staff salaries asynchronously in milliseconds in test-mode.
                      </p>

                      <div className="grid grid-cols-2 gap-3">
                        <div>
                          <label className="text-[10px] font-bold uppercase text-slate-400">Staff Members</label>
                          <input 
                            type="number" 
                            value={staffCount}
                            onChange={(e) => setStaffCount(Math.max(1, parseInt(e.target.value) || 1))}
                            className="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-xs font-bold text-midnight-deep outline-none"
                          />
                        </div>
                        <div>
                          <label className="text-[10px] font-bold uppercase text-slate-400">Avg Monthly (₦)</label>
                          <input 
                            type="number" 
                            value={staffSalary}
                            onChange={(e) => setStaffSalary(parseInt(e.target.value) || 0)}
                            className="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-xs font-bold text-midnight-deep outline-none"
                          />
                        </div>
                      </div>

                      <div className="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-2 text-xs">
                        <div className="flex justify-between">
                          <span className="text-slate-400">Total Payroll:</span>
                          <span className="font-extrabold text-midnight-deep">{formattedMoney(staffCount * staffSalary)}</span>
                        </div>
                        <div className="flex justify-between">
                          <span className="text-slate-400">CheckoutPay Batch fee (static flat):</span>
                          <span className="font-bold text-slate-700">₦250 flat</span>
                        </div>
                      </div>

                      {payoutProcessing ? (
                        <div className="text-center py-4 space-y-2">
                          <div className="w-8 h-8 rounded-full border-2 border-slate-100 border-t-brand-primary animate-spin mx-auto"></div>
                          <p className="text-[11px] font-bold text-slate-400">Routing batch NGN to all banks securely...</p>
                        </div>
                      ) : payoutFinished ? (
                        <div className="space-y-2">
                          <div className="bg-emerald-50 text-emerald-700 p-3 rounded-lg text-center text-xs font-bold border border-emerald-250 flex items-center justify-center gap-2">
                            <Check className="w-4 h-4 text-emerald-600" /> Distributed {formattedMoney(staffCount * staffSalary)} to {staffCount} recipients successfully!
                          </div>
                          <button
                            onClick={resetPayout}
                            className="text-center w-full text-[11px] font-bold text-brand-primary"
                          >
                            Calculate again
                          </button>
                        </div>
                      ) : (
                        <button
                          onClick={handleTriggerPayout}
                          className="w-full bg-brand-primary text-white hover:bg-brand-secondary py-3 text-xs font-bold rounded-xl transition-all"
                        >
                          Trigger Bulk Simulation
                        </button>
                      )}
                    </div>
                  )}

                </div>

              </motion.div>
            </motion.div>
          )}
        </AnimatePresence>
      </div>
    </section>
  );
}
