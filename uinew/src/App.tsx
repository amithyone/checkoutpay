import { useState } from "react";
import Navbar from "./components/Navbar";
import Hero from "./components/Hero";
import WhatsAppWallet from "./components/WhatsAppWallet";
import VirtualCard from "./components/VirtualCard";
import CommerceInfrastructure from "./components/CommerceInfrastructure";
import WooCommercePlugin from "./components/WooCommercePlugin";
import PricingCalculator from "./components/PricingCalculator";
import HowItWorksFAQ from "./components/HowItWorksFAQ";
import Footer from "./components/Footer";
import AuthModal from "./components/AuthModal";
import { Sparkles, X, ShieldCheck } from "lucide-react";
import { motion, AnimatePresence } from "motion/react";

export default function App() {
  // Authentication Modal control
  const [isAuthOpen, setIsAuthOpen] = useState(false);
  const [authMode, setAuthMode] = useState<'login' | 'register'>('register');
  const [merchantName, setMerchantName] = useState<string | null>(null);
  const [showDemo, setShowDemo] = useState(false);

  const handleOpenAuth = (mode: 'login' | 'register') => {
    setAuthMode(mode);
    setIsAuthOpen(true);
  };

  const handleAuthSuccess = (name: string) => {
    setMerchantName(name);
  };

  const handleSignOut = () => {
    setMerchantName(null);
  };

  const handleGoToDemo = () => {
    setShowDemo(true);
    // Smooth reset trigger flag
    setTimeout(() => {
      setShowDemo(false);
    }, 100);
  };

  return (
    <div className="min-h-screen bg-slate-50/50 flex flex-col justify-between selection:bg-brand-primary selection:text-white antialiased font-sans">
      
      {/* Dynamic Authorized Banner */}
      <AnimatePresence>
        {merchantName && (
          <motion.div 
            initial={{ height: 0, opacity: 0 }}
            animate={{ height: "auto", opacity: 1 }}
            exit={{ height: 0, opacity: 0 }}
            className="bg-brand-primary text-white text-xs py-3 px-6 text-center sticky top-0 z-50 flex items-center justify-center gap-3 border-b border-white/10"
          >
            <div className="flex items-center gap-1.5 font-bold">
              <span className="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
              <Sparkles className="w-3.5 h-3.5 text-brand-electric animate-spin" />
              Logged in: <span className="text-brand-electric font-black">{merchantName}</span> (Sandbox mode live)
            </div>
            <p className="hidden md:inline text-white/80 font-medium">
              - Feel free to simulate live webhooks, virtual transfers and rates inside any widget on this page!
            </p>
            <div className="flex items-center gap-2">
              <button 
                onClick={handleGoToDemo}
                className="bg-white/15 hover:bg-white/20 border border-white/10 px-2.5 py-1 rounded text-[10px] font-black uppercase tracking-wider transition-all"
              >
                Launch Playground
              </button>
              <button 
                onClick={handleSignOut}
                className="text-white/60 hover:text-white hover:underline text-[10px] font-bold"
              >
                Sign Out
              </button>
            </div>
          </motion.div>
        )}
      </AnimatePresence>

      {/* Navigation bar */}
      <Navbar 
        onOpenAuth={handleOpenAuth} 
        merchantName={merchantName} 
        onSignOut={handleSignOut}
        onOpenDemo={handleGoToDemo}
      />

      {/* Main landing sections */}
      <div className="flex-1 w-full relative">
        
        {/* Decorative Grid vector pattern */}
        <div className="absolute inset-0 bg-[linear-gradient(to_right,#8080800a_1px,transparent_1px),linear-gradient(to_bottom,#8080800a_1px,transparent_1px)] bg-[size:14px_24px] pointer-events-none -z-10"></div>
        
        <Hero 
          onOpenAuth={handleOpenAuth} 
          showDemoInitially={showDemo}
        />
        
        <WhatsAppWallet />
        
        <VirtualCard />
        
        <CommerceInfrastructure />
        
        <WooCommercePlugin />
        
        <PricingCalculator />
        
        <HowItWorksFAQ />
      </div>

      {/* Structured legal footer */}
      <Footer />

      {/* Portal Auth control Modal */}
      <AuthModal 
        isOpen={isAuthOpen}
        onClose={() => setIsAuthOpen(false)}
        initialMode={authMode}
        onAuthSuccess={handleAuthSuccess}
      />

    </div>
  );
}
