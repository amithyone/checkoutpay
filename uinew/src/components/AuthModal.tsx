import React, { useState } from "react";
import { X, Mail, Lock, Briefcase, Check, ShieldAlert, HeartHandshake } from "lucide-react";
import { motion } from "motion/react";

interface AuthModalProps {
  isOpen: boolean;
  onClose: () => void;
  initialMode: 'login' | 'register';
  onAuthSuccess: (businessName: string) => void;
}

export default function AuthModal({ isOpen, onClose, initialMode, onAuthSuccess }: AuthModalProps) {
  const [mode, setMode] = useState<'login' | 'register'>(initialMode);
  
  // Form variables
  const [businessName, setBusinessName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [payoutBank, setPayoutBank] = useState("Providus Bank");
  const [agreeTerms, setAgreeTerms] = useState(true);

  // Status variables
  const [submitted, setSubmitted] = useState(false);

  if (!isOpen) return null;

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitted(true);
    
    setTimeout(() => {
      onAuthSuccess(mode === 'register' ? (businessName || "New Merchant Org") : "Registered Merchant");
      setSubmitted(false);
      onClose();
    }, 2000);
  };

  return (
    <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <motion.div 
        initial={{ scale: 0.95, opacity: 0 }}
        animate={{ scale: 1, opacity: 1 }}
        exit={{ scale: 0.95, opacity: 0 }}
        className="bg-white rounded-[2rem] max-w-md w-full shadow-2xl relative border border-slate-100 overflow-hidden"
      >
        
        {/* Banner strip */}
        <div className="bg-brand-primary p-6 text-white relative">
          <button 
            onClick={onClose}
            className="absolute top-4 right-4 bg-white/10 hover:bg-white/20 p-2 rounded-xl transition-all text-white"
          >
            <X className="w-4 h-4" />
          </button>
          
          <div className="space-y-1">
            <h4 className="text-xl font-bold tracking-tight">
              {mode === 'register' ? 'Register Sandbox Account' : 'Merchant API Login'}
            </h4>
            <p className="text-[11px] text-white/75 font-semibold">
              {mode === 'register' ? 'Set up NNG matching with METRAVON' : 'Authenticate system credentials securely'}
            </p>
          </div>
          <div className="absolute bottom-0 right-0 p-4 opacity-10">
            <HeartHandshake className="w-20 h-20" />
          </div>
        </div>

        {/* Form area */}
        <form onSubmit={handleSubmit} className="p-6 md:p-8 space-y-5">
          {submitted ? (
            <div className="py-12 flex flex-col items-center justify-center space-y-4">
              <div className="w-12 h-12 rounded-full border-2 border-slate-100 border-t-brand-primary animate-spin"></div>
              <p className="text-xs font-bold text-slate-500">
                {mode === 'register' ? 'Initializing payout matching keys...' : 'Validating account token...'}
              </p>
            </div>
          ) : (
            <>
              {mode === 'register' && (
                <div className="space-y-1.5">
                  <label className="text-[10px] font-bold uppercase text-slate-400">Registered Business Name</label>
                  <div className="relative">
                    <Briefcase className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-450" />
                    <input 
                      type="text" 
                      required
                      placeholder="e.g. Amadi Enterprise Corp"
                      value={businessName}
                      onChange={(e) => setBusinessName(e.target.value)}
                      className="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 pl-10 pr-4 text-xs font-semibold text-midnight-deep outline-none focus:border-brand-primary focus:bg-white"
                    />
                  </div>
                </div>
              )}

              <div className="space-y-1.5">
                <label className="text-[10px] font-bold uppercase text-slate-400">Business E-mail Address</label>
                <div className="relative">
                  <Mail className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-450" />
                  <input 
                    type="email" 
                    required
                    placeholder="you@company.com.ng"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    className="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 pl-10 pr-4 text-xs font-semibold text-midnight-deep outline-none focus:border-brand-primary focus:bg-white"
                  />
                </div>
              </div>

              <div className="space-y-1.5">
                <label className="text-[10px] font-bold uppercase text-slate-400">Secure Account Password</label>
                <div className="relative">
                  <Lock className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-450" />
                  <input 
                    type="password" 
                    required
                    placeholder="••••••••••••"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    className="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 pl-10 pr-4 text-xs font-semibold text-midnight-deep outline-none focus:border-brand-primary focus:bg-white"
                  />
                </div>
              </div>

              {mode === 'register' && (
                <div className="space-y-1.5">
                  <label className="text-[10px] font-bold uppercase text-slate-400">Preferred Settlement Receiver Bank</label>
                  <select
                    value={payoutBank}
                    onChange={(e) => setPayoutBank(e.target.value)}
                    className="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-3 text-xs font-bold text-midnight-deep outline-none focus:border-brand-primary focus:bg-white"
                  >
                    <option value="Providus Bank">Providus Bank (Instant CBN-mesh)</option>
                    <option value="Wema Bank">Wema Bank (ALAT routing)</option>
                    <option value="Sterling Bank">Sterling Bank</option>
                    <option value="Zenith Bank">Zenith Bank Plc</option>
                    <option value="GTBank">Guaranty Trust Bank</option>
                  </select>
                </div>
              )}

              {mode === 'register' && (
                <label className="flex items-start gap-2.5 cursor-pointer pt-1">
                  <input 
                    type="checkbox" 
                    checked={agreeTerms}
                    onChange={(e) => setAgreeTerms(e.target.checked)}
                    className="rounded border-slate-300 text-brand-primary focus:ring-brand-primary/20 mt-0.5" 
                  />
                  <span className="text-[10px] text-slate-400 font-semibold leading-normal">
                    Agree to terms & automated METRAVON licensing fee matching agreements with the CBN.
                  </span>
                </label>
              )}

              <button
                type="submit"
                disabled={mode === 'register' && !agreeTerms}
                className="w-full bg-brand-primary hover:bg-brand-secondary text-white py-3.5 rounded-xl text-xs font-bold transition-all shadow-md active:scale-98 disabled:opacity-50"
              >
                {mode === 'register' ? 'Initialize Merchant Key Registration' : 'Secure Vault Log In'}
              </button>

              <div className="text-center pt-2">
                {mode === 'register' ? (
                  <p className="text-[11px] font-semibold text-slate-400">
                    Already registered?{" "}
                    <button 
                      type="button" 
                      onClick={() => setMode('login')}
                      className="text-brand-electric font-bold hover:underline"
                    >
                      Login of Business
                    </button>
                  </p>
                ) : (
                  <p className="text-[11px] font-semibold text-slate-400">
                    New to CheckoutPay?{" "}
                    <button 
                      type="button" 
                      onClick={() => setMode('register')}
                      className="text-brand-electric font-bold hover:underline"
                    >
                      Register sandbox
                    </button>
                  </p>
                )}
              </div>
            </>
          )}
        </form>

      </motion.div>
    </div>
  );
}
