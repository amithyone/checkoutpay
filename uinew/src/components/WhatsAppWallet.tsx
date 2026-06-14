import { useState, useEffect } from "react";
import { 
  Send, 
  MessageSquare, 
  Check, 
  CheckCheck, 
  Smartphone, 
  Sparkles, 
  CreditCard,
  User,
  X
} from "lucide-react";
import { motion, AnimatePresence } from "motion/react";

interface ChatBubble {
  id: string;
  sender: 'user' | 'bot';
  text: string;
  timestamp: string;
  type?: 'text' | 'confirmation' | 'success';
}

export default function WhatsAppWallet() {
  const [messages, setMessages] = useState<ChatBubble[]>([
    { id: '1', sender: 'user', text: "Pay ₦5,000 to John Doe", timestamp: "12:14 PM" },
    { id: '2', sender: 'bot', text: "Send NGN 5,000 to John Doe? Card ****4429 will be charged.", timestamp: "12:14 PM", type: 'confirmation' },
  ]);
  const [isTyping, setIsTyping] = useState(false);
  const [confirmed, setConfirmed] = useState(false);

  const presets = [
    { label: "💸 Send ₦2,500 to Lola", prompt: "Send ₦2,500 to Lola" },
    { label: "🔌 Pay ₦10,000 Ikeja Electric", prompt: "Pay ₦10,000 Ikeja Electric" },
    { label: "📶 Buy ₦1,500 MTN Airtime", prompt: "Buy ₦1,500 MTN Airtime" },
  ];

  const triggerBotResponse = (userText: string) => {
    setIsTyping(true);
    setConfirmed(false);
    
    setTimeout(() => {
      let botText = "";
      let type: 'text' | 'confirmation' = 'text';

      if (userText.includes('Lola')) {
        botText = "Send NGN 2,500 to Lola? Please reply 'Yes' to authorize from your wallet.";
        type = 'confirmation';
      } else if (userText.includes('Electric')) {
        botText = "Pay NGN 10,005 (incl. service charge) to Ikeja Electric? Please reply 'Yes' or click verify link.";
        type = 'confirmation';
      } else if (userText.includes('Airtime')) {
        botText = "Buy NGN 1,500 MTN Airtime for yourself? Reply 'Yes' to fund from CheckoutPay balance.";
        type = 'confirmation';
      } else {
        botText = "Sorry, I can only understand payments, transfers & invoices. Example: 'Send ₦1,000 to David'.";
      }

      setMessages(prev => [
        ...prev,
        {
          id: Math.random().toString(),
          sender: 'bot',
          text: botText,
          timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
          type
        }
      ]);
      setIsTyping(false);
    }, 1200);
  };

  const handleSendPreset = (prompt: string) => {
    const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    setMessages(prev => [
      ...prev,
      { id: Math.random().toString(), sender: 'user', text: prompt, timestamp: time }
    ]);
    triggerBotResponse(prompt);
  };

  const handleConfirm = () => {
    const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    setMessages(prev => [
      ...prev,
      { id: Math.random().toString(), sender: 'user', text: "Yes, confirm payment.", timestamp: time }
    ]);
    
    setIsTyping(true);
    setTimeout(() => {
      setMessages(prev => [
        ...prev,
        { 
          id: Math.random().toString(), 
          sender: 'bot', 
          text: "Transaction Successful! NNG paid successfully. Invoice has been sent to your mail.", 
          timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
          type: 'success' 
        }
      ]);
      setIsTyping(false);
      setConfirmed(true);
    }, 1500);
  };

  const handleClear = () => {
    setMessages([
      { id: '1', sender: 'user', text: "Pay ₦5,000 to John Doe", timestamp: "12:14 PM" },
      { id: '2', sender: 'bot', text: "Send NGN 5,000 to John Doe? Card ****4429 will be charged.", timestamp: "12:14 PM", type: 'confirmation' }
    ]);
    setConfirmed(false);
  };

  return (
    <section id="products" className="py-24 bg-gradient-to-b from-slate-50 to-white border-y border-slate-100">
      <div className="px-6 md:px-12 max-w-7xl mx-auto">
        
        {/* Full container styled similarly to the premium banner */}
        <div className="bg-brand-primary rounded-[2.5rem] overflow-hidden relative group shadow-2xl shadow-brand-primary/10">
          
          {/* Absolute decorative meshes */}
          <div className="absolute top-0 right-0 w-[50%] h-full bg-[radial-gradient(circle_at_top_right,_rgba(255,255,255,0.1)_0%,_transparent_60%)] pointer-events-none"></div>
          <div className="absolute -bottom-24 -left-20 w-80 h-80 bg-brand-electric/30 rounded-full blur-3xl pointer-events-none"></div>

          <div className="grid lg:grid-cols-2 gap-8 items-center">
            
            {/* Left Content Column */}
            <div className="p-8 md:p-16 space-y-8 z-10">
              <span className="font-semibold text-xs text-brand-electric bg-white/10 border border-white/20 uppercase tracking-widest px-4 py-1.5 rounded-full inline-block">
                Flagship Feature
              </span>
              
              <h2 className="text-3xl md:text-5xl font-black text-white leading-tight font-sans">
                WhatsApp Wallet
              </h2>
              
              <p className="text-sm md:text-base text-brand-primary/10 text-white/90 leading-relaxed font-medium max-w-md">
                Send money to bank accounts or WhatsApp contacts, buy airtime & more from the app you already use. Frictionless commerce, right where your customers are. In partnership with METRAVON LTD.
              </p>

              {/* Quick instructions */}
              <div className="space-y-3 pt-2">
                <p className="text-xs font-bold text-blue-200 uppercase tracking-wider">Try the Simulation Dashboard:</p>
                <div className="flex flex-wrap gap-2">
                  {presets.map((preset, idx) => (
                    <button
                      key={idx}
                      onClick={() => handleSendPreset(preset.prompt)}
                      className="bg-white/10 hover:bg-white/15 border border-white/10 text-white text-xs font-bold px-3 py-2 rounded-xl transition-all"
                    >
                      {preset.label}
                    </button>
                  ))}
                </div>
              </div>

              <div className="flex items-center gap-4 pt-4">
                <a 
                  href="#pricing"
                  className="bg-white text-brand-primary font-bold text-sm px-8 py-3.5 rounded-xl hover:bg-brand-electric-10 animate-pulse transition-all shadow-lg active:scale-98"
                >
                  Start Offline-pay
                </a>
                {messages.length > 2 && (
                  <button
                    onClick={handleClear}
                    className="text-white/80 hover:text-white border border-white/20 hover:bg-white/5 font-bold text-xs px-4 py-3 rounded-xl transition-all"
                  >
                    Reset Chat
                  </button>
                )}
              </div>
            </div>

            {/* Right Interactive Chat Panel Column */}
            <div className="relative p-6 md:p-10 flex items-center justify-center">
              
              {/* WhatsApp Interface Mock Container */}
              <div className="bg-[#E5DDD5] rounded-3xl w-full max-w-lg shadow-2xl overflow-hidden border border-slate-350 relative h-[450px] flex flex-col justify-between">
                
                {/* Simulated Header */}
                <div className="bg-[#075E54] text-white px-4 py-3.5 flex items-center justify-between shadow-md shrink-0">
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-full bg-brand-primary flex items-center justify-center text-white border border-white/20 shadow">
                      <MessageSquare className="w-5 h-5 text-white" />
                    </div>
                    <div>
                      <p className="font-bold text-sm leading-tight">CheckoutPay Verified Bot</p>
                      <p className="text-[10px] text-emerald-100 flex items-center gap-1">
                        <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-ping"></span>
                        Active integration
                      </p>
                    </div>
                  </div>
                  
                  <div className="text-[10px] font-black uppercase text-emerald-250 border border-emerald-400 bg-emerald-950/40 px-2 py-0.5 rounded tracking-wide">
                    SECURE 
                  </div>
                </div>

                {/* Simulated Chat Feed bg image overlay */}
                <div 
                  className="flex-1 p-4 overflow-y-auto space-y-4"
                  style={{
                    backgroundImage: 'radial-gradient(#dfdfdf 1px, transparent 1px)',
                    backgroundSize: '16px 16px',
                  }}
                >
                  {messages.map((msg) => (
                    <motion.div
                      key={msg.id}
                      initial={{ opacity: 0, scale: 0.95, y: 5 }}
                      animate={{ opacity: 1, scale: 1, y: 0 }}
                      className={`flex ${msg.sender === 'user' ? 'justify-end' : 'justify-start'}`}
                    >
                      <div 
                        className={`p-3.5 rounded-2xl max-w-[85%] relative shadow-sm ${
                          msg.sender === 'user'
                            ? 'bg-[#DCF8C6] text-slate-850 rounded-tr-none'
                            : 'bg-white text-slate-850 rounded-tl-none'
                        }`}
                      >
                        {/* Body content */}
                        <p className="text-xs font-semibold leading-relaxed break-words">{msg.text}</p>
                        
                        {/* Dynamic Button in Confirmation Box */}
                        {msg.type === 'confirmation' && !confirmed && (
                          <div className="mt-3.5 pt-2 border-t border-slate-100 flex gap-2">
                            <button
                              onClick={handleConfirm}
                              className="bg-brand-primary text-white text-[11px] font-bold px-4 py-2 rounded-lg hover:bg-brand-secondary transition-all"
                            >
                              Yes, confirm payment.
                            </button>
                            <button
                              onClick={() => {
                                setMessages(prev => [...prev, { id: Math.random().toString(), sender: 'bot', text: 'Transaction cancelled.', timestamp: 'Now' }]);
                              }}
                              className="bg-slate-100 text-slate-500 text-[11px] font-semibold px-3 py-2 rounded-lg hover:bg-slate-200 transition-all"
                            >
                              Cancel
                            </button>
                          </div>
                        )}

                        {/* Inline Success Banner */}
                        {msg.type === 'success' && (
                          <div className="mt-3 bg-emerald-500/10 border border-emerald-500/25 p-2 rounded-xl flex items-center gap-2 text-[11px] text-emerald-700 font-bold">
                            <div className="w-5 h-5 bg-emerald-500 rounded-full flex items-center justify-center text-white shrink-0">
                              <Check className="w-3.5 h-3.5 stroke-[3]" />
                            </div>
                            Verified transaction: METRAVON INH_89
                          </div>
                        )}

                        {/* Time stamp */}
                        <div className="flex items-center justify-end gap-1.5 mt-1">
                          <span className="text-[9px] text-slate-400 font-semibold">{msg.timestamp}</span>
                          {msg.sender === 'user' && (
                            <CheckCheck className="w-3 h-3 text-brand-electric stroke-[2.5]" />
                          )}
                        </div>
                      </div>
                    </motion.div>
                  ))}

                  {isTyping && (
                    <div className="flex justify-start">
                      <div className="bg-white p-3 rounded-2xl rounded-tl-none shadow-sm flex items-center gap-1.5">
                        <span className="w-1.5 h-1.5 bg-slate-400 rounded-full animate-bounce"></span>
                        <span className="w-1.5 h-1.5 bg-slate-400 rounded-full animate-bounce delay-150"></span>
                        <span className="w-1.5 h-1.5 bg-slate-400 rounded-full animate-bounce delay-300"></span>
                      </div>
                    </div>
                  )}
                </div>

                {/* Simulated input bar */}
                <div className="bg-[#F0F0F0] p-3 flex items-center gap-2 border-t border-slate-200 shrink-0">
                  <div className="flex-1 bg-white rounded-full py-2.5 px-4 text-xs font-semibold text-slate-400 border border-slate-200">
                    Use preset buttons to simulate...
                  </div>
                  <div className="w-10 h-10 rounded-full bg-brand-primary flex items-center justify-center text-white shadow shadow-brand-primary/20">
                    <Send className="w-4 h-4 ml-0.5 text-white" />
                  </div>
                </div>

              </div>
            </div>

          </div>
        </div>
      </div>
    </section>
  );
}
