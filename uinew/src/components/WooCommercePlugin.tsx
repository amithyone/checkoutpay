import React, { useState } from "react";
import { 
  Download, 
  Settings, 
  Terminal, 
  CheckCircle, 
  Code, 
  Cpu, 
  Check, 
  Play,
  RotateCcw,
  Sparkles
} from "lucide-react";
import { motion } from "motion/react";

export default function WooCommercePlugin() {
  const [terminalTab, setTerminalTab] = useState<'install' | 'settings' | 'status'>('install');
  
  // Settings tab variables
  const [apiKey, setApiKey] = useState('cp_live_demo_replace_with_your_secret_key');
  const [feeBearer, setFeeBearer] = useState<'merchant' | 'customer'>('merchant');
  const [activated, setActivated] = useState(false);

  const handleDownload = (e: React.MouseEvent) => {
    e.preventDefault();
    alert("CheckoutPay WooCommerce plugin ZIP download started! (Check folder 'checkoutpay-woo-v1.4.6.zip' in sandbox exports)");
  };

  return (
    <section id="woocommerce" className="py-24 bg-slate-50 border-t border-slate-200/50">
      <div className="px-6 md:px-12 max-w-7xl mx-auto">
        <div className="grid lg:grid-cols-2 gap-16 items-center">
          
          {/* Left Column: Interactive Terminal Sandbox Widget */}
          <div className="relative">
            <div className="bg-midnight-deep rounded-2xl p-6 md:p-8 shadow-2xl relative z-10 border border-slate-800">
              
              {/* Terminal Title Bar */}
              <div className="flex items-center justify-between mb-6 pb-4 border-b border-white/5">
                <div className="flex gap-2">
                  <div className="w-3 h-3 rounded-full bg-[#FF5F56]" title="Close"></div>
                  <div className="w-3 h-3 rounded-full bg-[#FFBD2E]" title="Minimize"></div>
                  <div className="w-3 h-3 rounded-full bg-[#27C93F]" title="Expand"></div>
                </div>
                
                {/* Embedded Tab selectors inside terminal */}
                <div className="flex bg-white/5 border border-white/10 p-0.5 rounded-lg">
                  <button 
                    onClick={() => setTerminalTab('install')}
                    className={`px-3 py-1 rounded text-[10px] font-bold tracking-wide transition-all ${
                      terminalTab === 'install' ? 'bg-white/10 text-white' : 'text-white/40 hover:text-white/75'
                    }`}
                  >
                    Guide
                  </button>
                  <button 
                    onClick={() => setTerminalTab('settings')}
                    className={`px-3 py-1 rounded text-[10px] font-bold tracking-wide transition-all ${
                      terminalTab === 'settings' ? 'bg-white/10 text-white' : 'text-white/40 hover:text-white/75'
                    }`}
                  >
                    API Config
                  </button>
                  <button 
                    onClick={() => setTerminalTab('status')}
                    className={`px-3 py-1 rounded text-[10px] font-bold tracking-wide transition-all ${
                      terminalTab === 'status' ? 'bg-white/10 text-white' : 'text-white/40 hover:text-white/75'
                    }`}
                  >
                    Verify System
                  </button>
                </div>

                <span className="text-[10px] text-white/40 font-mono tracking-widest hidden sm:inline">
                  v1.4.6
                </span>
              </div>

              {/* Terminal Main Window */}
              <div className="min-h-[220px]">
                {terminalTab === 'install' && (
                  <div className="space-y-4">
                    <div className="flex items-center gap-3">
                      <div className="w-9 h-9 bg-brand-primary/20 text-brand-electric rounded-lg flex items-center justify-center">
                        <Terminal className="w-5 h-5 text-brand-electric" />
                      </div>
                      <div>
                        <p className="text-xs font-bold text-white">CheckoutPay for WooCommerce</p>
                        <p className="text-[10px] text-white/45 font-semibold">Official Open-Source Payment Gateway Plugin</p>
                      </div>
                    </div>

                    <div className="font-mono text-xs text-white/80 space-y-2 pt-2 leading-relaxed">
                      <p className="text-emerald-400">// Installation Sequence</p>
                      <p><span className="text-white/50">01.</span> Upload &amp; Activate plugin files</p>
                      <p><span className="text-white/50">02.</span> Paste API secret key inside WP dashboard</p>
                      <p><span className="text-white/50">03.</span> Tick settlement preferences</p>
                      <p><span className="text-white/50">04.</span> Save settings — ready to process customer matching!</p>
                    </div>
                  </div>
                )}

                {terminalTab === 'settings' && (
                  <div className="space-y-4 font-sans text-xs">
                    <p className="text-emerald-400 font-mono text-[10px]">// Simulated Wordpress Settings Sheet</p>
                    
                    <div className="space-y-3">
                      <div className="space-y-1.5">
                        <label className="text-white/60 text-[10px] font-bold uppercase tracking-wider block">API SECRET KEY</label>
                        <input 
                          type="password" 
                          value={apiKey}
                          onChange={(e) => setApiKey(e.target.value)}
                          className="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-white font-mono text-xs focus:border-brand-electric outline-none"
                        />
                      </div>

                      <div className="space-y-1.5">
                        <label className="text-white/60 text-[10px] font-bold uppercase tracking-wider block">Transaction Fee Bearer</label>
                        <div className="grid grid-cols-2 gap-2">
                          <button
                            onClick={() => setFeeBearer('merchant')}
                            className={`py-2 px-3 rounded-lg font-bold border transition-all text-center ${
                              feeBearer === 'merchant'
                                ? 'bg-brand-primary text-white border-brand-electric shadow'
                                : 'bg-white/5 text-white/60 border-white/10'
                            }`}
                          >
                            Me (Merchant pays 1% + ₦50)
                          </button>
                          <button
                            onClick={() => setFeeBearer('customer')}
                            className={`py-2 px-3 rounded-lg font-bold border transition-all text-center ${
                              feeBearer === 'customer'
                                ? 'bg-brand-primary text-white border-brand-electric shadow'
                                : 'bg-white/5 text-white/60 border-white/10'
                            }`}
                          >
                            Customer pays gateway fee
                          </button>
                        </div>
                      </div>
                    </div>

                    {activated ? (
                      <div className="bg-emerald-500/10 border border-emerald-500/25 p-2 rounded-lg text-emerald-400 font-bold font-mono text-[10px] text-center">
                        ✓ Integration Activated! Merchant checkout payload verified.
                      </div>
                    ) : (
                      <button
                        onClick={() => {
                          setActivated(true);
                          setTimeout(() => setActivated(false), 3500);
                        }}
                        className="w-full bg-brand-electric hover:bg-brand-primary text-white font-bold py-2.5 rounded-lg transition-all text-xs"
                      >
                        Register API Key Configuration
                      </button>
                    )}
                  </div>
                )}

                {terminalTab === 'status' && (
                  <div className="font-mono text-xs text-white/80 space-y-2 pt-2">
                    <p className="text-emerald-400 text-[10px]">// Run Diagnostics check</p>
                    <div className="p-3 bg-white/5 border border-white/10 rounded-xl space-y-1.5 text-[10px]">
                      <div className="flex justify-between items-center">
                        <span>METRAVON network ping:</span>
                        <span className="text-emerald-400 font-bold">14ms OK</span>
                      </div>
                      <div className="flex justify-between items-center">
                        <span>CBN gateway API handshake:</span>
                        <span className="text-emerald-400 font-bold">Authenticated</span>
                      </div>
                      <div className="flex justify-between items-center">
                        <span>PCI DSS webhook ssl:</span>
                        <span className="text-emerald-400 font-bold">SSL_VERIFIED_TLS1.3</span>
                      </div>
                      <div className="flex justify-between items-center">
                        <span>Database webhook pool:</span>
                        <span className="text-emerald-400 font-bold">Broadcasting OK</span>
                      </div>
                    </div>
                    <p className="text-[10px] text-white/45 leading-relaxed">
                      WP Core: 5.8+ recognized. WooCommerce db pool compatible. Ready for production matching.
                    </p>
                  </div>
                )}
              </div>

            </div>
            
            {/* Absolute blur backdrops */}
            <div className="absolute -top-10 -right-10 w-44 h-44 bg-brand-electric/10 blur-3xl pointer-events-none -z-0"></div>
          </div>

          {/* Right Column: Descriptions & Downloads */}
          <div className="space-y-8">
            <span className="font-semibold text-xs text-brand-primary bg-brand-primary/10 border border-brand-primary/20 uppercase tracking-widest px-4 py-1.5 rounded-full inline-block">
              WordPress Plugin
            </span>
            
            <h2 className="text-3xl md:text-5xl font-black text-midnight-deep tracking-tight">
              Seamless Integration with WooCommerce
            </h2>
            
            <p className="text-slate-600 font-medium leading-relaxed text-sm md:text-base">
              Install the plugin, paste your API Secret Key, and you're live. Works out of the box with WordPress 5.8+ and WooCommerce 7.0+. No heavy coding needed to get paid in Nigeria.
            </p>

            {/* Bullets */}
            <div className="space-y-6">
              
              <div className="flex items-start gap-4">
                <div className="p-2 bg-brand-primary/5 text-brand-primary border border-brand-primary/10 rounded-xl shrink-0 mt-0.5">
                  <Download className="w-5 h-5 text-brand-electric" />
                </div>
                <div>
                  <h4 className="font-bold text-midnight-deep text-sm sm:text-base">One-Click Install</h4>
                  <p className="text-xs font-semibold text-slate-500 leading-relaxed">
                    Download and activate instantly from your standard wordpress plugins repository directory.
                  </p>
                </div>
              </div>

              <div className="flex items-start gap-4">
                <div className="p-2 bg-brand-primary/5 text-brand-primary border border-brand-primary/10 rounded-xl shrink-0 mt-0.5">
                  <Settings className="w-5 h-5 text-brand-electric" />
                </div>
                <div>
                  <h4 className="font-bold text-midnight-deep text-sm sm:text-base">Charge Management</h4>
                  <p className="text-xs font-semibold text-slate-500 leading-relaxed">
                    Flexible settings. Choose exactly who pays the CheckoutPay 1% processing fee — you or your customer at payment checkpoint.
                  </p>
                </div>
              </div>

            </div>

            {/* Action buttons */}
            <div className="flex flex-wrap gap-4 pt-4">
              <button 
                onClick={handleDownload}
                className="bg-midnight-deep text-white font-semibold text-xs px-8 py-4 rounded-xl flex items-center gap-2 hover:bg-slate-850 active:scale-98 transition-all shadow"
              >
                Download Plugin <Download className="w-4 h-4 text-white" />
              </button>
              <button 
                onClick={() => {
                  alert("Opening official WooCommerce repository specification metadata. Built in active partnership with METRAVON LTD.");
                }}
                className="border border-slate-200 font-semibold text-xs text-midnight-deep bg-white hover:bg-slate-50 px-8 py-4 rounded-xl transition-all"
              >
                Plugin details
              </button>
            </div>

          </div>

        </div>
      </div>
    </section>
  );
}
