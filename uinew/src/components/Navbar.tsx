import { useState } from "react";
import { Menu, X, ArrowRight, ShieldCheck } from "lucide-react";
import { motion, AnimatePresence } from "motion/react";

interface NavbarProps {
  onOpenDemo: () => void;
  onOpenAuth: (mode: 'login' | 'register') => void;
  merchantName: string | null;
  onSignOut: () => void;
}

export default function Navbar({ onOpenDemo, onOpenAuth, merchantName, onSignOut }: NavbarProps) {
  const [isOpen, setIsOpen] = useState(false);

  const navLinks = [
    { name: "Products", href: "#products" },
    { name: "Virtual Card", href: "#virtual-card" },
    { name: "WooCommerce", href: "#woocommerce" },
    { name: "Pricing", href: "#pricing" },
    { name: "FAQs", href: "#faqs" },
  ];

  return (
    <>
      <header className="fixed top-0 w-full z-50 bg-white/85 backdrop-blur-xl border-b border-slate-100 shadow-sm transition-all duration-300">
        <nav className="flex justify-between items-center h-20 px-6 max-w-7xl mx-auto">
          {/* Logo & Brand */}
          <div className="flex items-center gap-10">
            <a href="#" className="flex items-center gap-2 group">
              <div className="w-10 h-10 rounded-xl bg-brand-primary flex items-center justify-center shadow-lg shadow-brand-primary/25 group-hover:scale-105 transition-transform">
                <ShieldCheck className="w-6 h-6 text-white" />
              </div>
              <div className="flex flex-col">
                <span className="font-sans text-2xl font-black tracking-tight text-midnight-deep leading-none">
                  Checkout<span className="text-brand-electric">Pay</span>
                </span>
                <span className="text-[10px] font-semibold text-slate-400 tracking-wider uppercase">
                  Metravon Partner
                </span>
              </div>
            </a>

            {/* Desktop Navigation */}
            <div className="hidden md:flex items-center gap-8 font-medium text-slate-600">
              {navLinks.map((link) => (
                <a
                  key={link.name}
                  href={link.href}
                  className="hover:text-brand-primary transition-colors duration-200 text-sm relative py-2 group"
                >
                  {link.name}
                  <span className="absolute bottom-0 left-0 w-0 h-0.5 bg-brand-primary transition-all duration-300 group-hover:w-full"></span>
                </a>
              ))}
            </div>
          </div>

          {/* Desktop Call to Actions */}
          <div className="hidden md:flex items-center gap-4">
            {merchantName ? (
              <>
                <span className="text-xs font-black text-brand-primary bg-brand-primary/10 px-3.5 py-1.5 rounded-full border border-brand-primary/20 flex items-center gap-1.5">
                  <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                  {merchantName}
                </span>
                <button 
                  onClick={onSignOut}
                  className="text-xs font-bold text-slate-500 hover:text-red-500 transition-colors px-3 py-2 rounded-xl"
                >
                  Sign Out
                </button>
              </>
            ) : (
              <>
                <button 
                  onClick={() => onOpenAuth('login')}
                  className="text-sm font-semibold text-slate-600 hover:text-brand-primary transition-colors px-4 py-2 hover:bg-slate-50 rounded-xl"
                >
                  Login
                </button>
                <button 
                  onClick={() => onOpenAuth('register')}
                  className="bg-brand-primary text-white text-sm font-semibold px-6 py-3 rounded-xl hover:bg-brand-secondary transition-all shadow-md shadow-brand-primary/10 active:scale-98"
                >
                  Create Account
                </button>
              </>
            )}
            <button
              onClick={onOpenDemo}
              className="bg-emerald-50 text-emerald-700 text-xs font-bold px-4 py-3 rounded-xl border border-emerald-100 hover:bg-emerald-100 transition-all flex items-center gap-1.5"
            >
              <span className="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-ping"></span>
              Live Demo
            </button>
          </div>

          {/* Mobile Hamburguer */}
          <div className="md:hidden flex items-center gap-3">
            <button
              onClick={onOpenDemo}
              className="bg-emerald-50 text-emerald-700 text-xs font-bold px-3 py-2 rounded-lg border border-emerald-100 hover:bg-emerald-100 transition-all flex items-center gap-1"
            >
              <span className="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-ping"></span>
              Demo
            </button>
            <button
              onClick={() => setIsOpen(!isOpen)}
              className="p-2 text-slate-600 hover:text-black rounded-lg hover:bg-slate-100 transition-colors"
              aria-label="Toggle Menu"
            >
              {isOpen ? <X className="w-6 h-6" /> : <Menu className="w-6 h-6" />}
            </button>
          </div>
        </nav>

        {/* Mobile Navigation Drawer */}
        <AnimatePresence>
          {isOpen && (
            <motion.div
              initial={{ opacity: 0, height: 0 }}
              animate={{ opacity: 1, height: "auto" }}
              exit={{ opacity: 0, height: 0 }}
              transition={{ duration: 0.25 }}
              className="md:hidden border-t border-slate-100 bg-white overflow-hidden shadow-xl"
            >
              <div className="px-6 py-6 space-y-4 flex flex-col">
                {navLinks.map((link) => (
                  <a
                    key={link.name}
                    href={link.href}
                    onClick={() => setIsOpen(false)}
                    className="text-base font-semibold text-slate-700 hover:text-brand-primary py-2 transition-colors border-b border-slate-50 last:border-0"
                  >
                    {link.name}
                  </a>
                ))}
                <div className="pt-4">
                  {merchantName ? (
                    <div className="space-y-3">
                      <div className="text-xs font-bold text-slate-400 bg-slate-50 p-3 rounded-lg border border-slate-100">
                        Signed in: <span className="text-brand-primary">{merchantName}</span>
                      </div>
                      <button
                        onClick={() => {
                          setIsOpen(false);
                          onSignOut();
                        }}
                        className="w-full text-center font-bold text-red-500 bg-red-50 py-3 rounded-xl text-sm"
                      >
                        Sign Out Account
                      </button>
                    </div>
                  ) : (
                    <div className="grid grid-cols-2 gap-4">
                      <button
                        onClick={() => {
                          setIsOpen(false);
                          onOpenAuth('login');
                        }}
                        className="w-full text-center font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 py-3 rounded-xl text-sm"
                      >
                        Login
                      </button>
                      <button
                        onClick={() => {
                          setIsOpen(false);
                          onOpenAuth('register');
                        }}
                        className="w-full text-center font-semibold bg-brand-primary text-white hover:bg-brand-secondary py-3 rounded-xl text-sm"
                      >
                        Create Account
                      </button>
                    </div>
                  )}
                </div>
              </div>
            </motion.div>
          )}
        </AnimatePresence>
      </header>
    </>
  );
}
