import { useState } from "react";
import { 
  ChevronDown, 
  HelpCircle, 
  ShieldCheck, 
  Plus, 
  Minus,
  Sparkles,
  Info
} from "lucide-react";
import { motion, AnimatePresence } from "motion/react";

interface FAQItem {
  id: string;
  question: string;
  answer: string;
}

export default function HowItWorksFAQ() {
  const [openFAQ, setOpenFAQ] = useState<string | null>('faq-1');

  const steps = [
    { num: 1, title: "Create Account", desc: "Sign up for free in minutes." },
    { num: 2, title: "Integrate Keys", desc: "Connect via API or WooCommerce Plugin." },
    { num: 3, title: "Accept Payments", desc: "Start receiving NGN instantly." },
    { num: 4, title: "Get Paid", desc: "Automated payouts directly to your bank account." }
  ];

  const faqs: FAQItem[] = [
    {
      id: "faq-1",
      question: "Is CheckoutPay secure?",
      answer: "CheckoutPay is fully secure and PCI-DSS Compliant. In active technology partnership with METRAVON INNOVATION LTD, authorized and compliant with the CENTRAL BANK OF NIGERIA directives, all operations route through secure TLS 1.3 channels and AES-256 automated databases."
    },
    {
      id: "faq-2",
      question: "What are the settlement times?",
      answer: "Transaction settlements occur within T+1 (24 hours). Funds are aggregated securely and disbursed by automated batch processing networks directly to your local corporate bank account with no manual triggers required."
    },
    {
      id: "faq-3",
      question: "Can I accept international payments?",
      answer: "Yes. In addition to NGN bank transfers and USSD codes, our gateways easily process international Mastercard, Visa, and Discovery debit cards. Furthermore, you can instantly configure a USD Virtual Card funded via NGN."
    },
    {
      id: "faq-4",
      question: "Are there setup fees or monthly charges?",
      answer: "Absolutely not. There are zero signup fees, zero software licensing fees, and zero server maintenance costs. We only earn a transparent 1% + ₦50 per successful transaction, ensuring our success aligns directly with your sales."
    }
  ];

  const toggleFAQ = (id: string) => {
    setOpenFAQ(openFAQ === id ? null : id);
  };

  return (
    <section id="faqs" className="py-24 bg-slate-50 border-y border-slate-200/50">
      <div className="px-6 md:px-12 max-w-7xl mx-auto space-y-24">
        
        {/* How It Works Module */}
        <div className="space-y-16">
          <div className="text-center max-w-md mx-auto space-y-3">
            <h2 className="text-3xl font-black text-midnight-deep tracking-tight">How It Works</h2>
            <p className="text-xs font-semibold text-slate-400">Launch payments in 4 straightforward checkout checkpoints.</p>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 relative">
            
            {/* Horizontal timeline connector lines for desktop */}
            <div className="hidden lg:block absolute top-7 left-12 right-12 h-0.5 bg-slate-200 -z-0"></div>

            {steps.map((step, idx) => (
              <div key={idx} className="relative z-10 text-center space-y-4 group">
                {/* Step circle */}
                <div className="w-14 h-14 bg-brand-primary text-white text-base font-black rounded-full flex items-center justify-center mx-auto shadow-lg shadow-brand-primary/10 group-hover:scale-105 transition-transform">
                  {step.num}
                </div>

                <div className="space-y-1.5">
                  <h4 className="font-bold text-sm sm:text-base text-midnight-deep">{step.title}</h4>
                  <p className="text-xs font-semibold text-slate-500 leading-relaxed max-w-[200px] mx-auto">{step.desc}</p>
                </div>
              </div>
            ))}

          </div>
        </div>

        {/* FAQ Accordions & Compliance Section banner */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-16 items-start pt-12 border-t border-slate-200">
          
          {/* Left panel info */}
          <div className="space-y-8">
            <h2 className="text-3xl md:text-5xl font-black text-midnight-deep leading-tight">
              Trusted by Nigerian businesses of all sizes.
            </h2>
            
            <p className="text-slate-600 font-medium leading-relaxed text-sm md:text-base">
              CheckoutPay is built to provide reliable, enterprise-grade payment infrastructure for local developers and merchants. We operate in active licensing and technical oversight partnership with <strong className="text-midnight-deep font-bold">METRAVON INNOVATION LTD</strong>, complying with all security criteria of the CBN.
            </p>

            {/* Visual Credit card graphic */}
            <div className="relative w-full aspect-video max-w-md bg-gradient-to-tr from-midnight-deep to-slate-900 rounded-3xl p-6 flex flex-col justify-between shadow-2xl border border-slate-800/80 overflow-hidden group">
              
              {/* Card visual elements */}
              <div className="absolute top-0 right-0 w-44 h-44 bg-brand-electric/10 rounded-full blur-2xl group-hover:bg-brand-electric/15 transition-all"></div>
              
              <div className="flex justify-between items-center z-10">
                <div className="flex gap-1.5">
                  <div className="w-6 h-6 rounded-lg bg-white/10 flex items-center justify-center border border-white/10">
                    <ShieldCheck className="w-4 h-4 text-brand-electric" />
                  </div>
                  <span className="text-[10px] font-black uppercase text-white font-mono tracking-wider pt-1">CheckoutPay Premium</span>
                </div>
                <span className="text-[9px] font-black uppercase text-[#22C55E] tracking-wider">● Compliant</span>
              </div>

              <div className="space-y-4 z-10">
                <p className="text-white font-mono text-lg tracking-widest sm:text-xl">4532  8901  2345  6789</p>
                
                <div className="flex justify-between items-end text-[9px] font-mono text-slate-400">
                  <div>
                    <p className="uppercase font-bold tracking-wider text-slate-500">Card Holder</p>
                    <p className="font-bold text-slate-300">ELIZA K. THOMPSON</p>
                  </div>
                  <div className="text-right">
                    <p className="uppercase font-bold tracking-wider text-slate-500">Exp Date</p>
                    <p className="font-bold text-slate-300">10 / 28</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Right Accordion Panel */}
          <div className="space-y-4">
            {faqs.map((faq) => (
              <div 
                key={faq.id}
                className="bg-white border border-slate-205/85 rounded-2xl p-5 shadow-sm hover:shadow-md transition-all cursor-pointer"
                onClick={() => toggleFAQ(faq.id)}
              >
                <div className="flex justify-between items-center gap-4">
                  <span className="font-bold text-xs sm:text-sm text-midnight-deep">{faq.question}</span>
                  <div className="text-brand-primary">
                    {openFAQ === faq.id ? <Minus className="w-4 h-4" /> : <Plus className="w-4 h-4" />}
                  </div>
                </div>

                <AnimatePresence>
                  {openFAQ === faq.id && (
                    <motion.div
                      initial={{ height: 0, opacity: 0, marginTop: 0 }}
                      animate={{ height: "auto", opacity: 1, marginTop: 12 }}
                      exit={{ height: 0, opacity: 0, marginTop: 0 }}
                      transition={{ duration: 0.25 }}
                      className="overflow-hidden"
                    >
                      <p className="text-xs sm:text-sm text-slate-500 font-medium leading-relaxed pt-2 border-t border-slate-50">
                        {faq.answer}
                      </p>
                    </motion.div>
                  )}
                </AnimatePresence>
              </div>
            ))}
          </div>

        </div>

      </div>
    </section>
  );
}
