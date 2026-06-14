import { ShieldCheck, Lock, Globe, HelpCircle, Heart } from "lucide-react";

export default function Footer() {
  const currentYear = new Date().getFullYear();

  const footerSections = [
    {
      title: "Products",
      links: [
        { name: "WhatsApp Wallet", href: "#products" },
        { name: "Invoices", href: "#features" },
        { name: "Rentals", href: "#features" },
        { name: "Memberships", href: "#features" },
        { name: "Tickets", href: "#features" }
      ]
    },
    {
      title: "Integrate",
      links: [
        { name: "API Documentation", href: "#woocommerce" },
        { name: "WooCommerce Plugin", href: "#woocommerce" },
        { name: "Developers", href: "#woocommerce" },
        { name: "Dev Program", href: "#woocommerce" }
      ]
    },
    {
      title: "Learn",
      links: [
        { name: "FAQs", href: "#faqs" },
        { name: "Support Hub", href: "#faqs" },
        { name: "Blog Feed", href: "#faqs" },
        { name: "Network Status", href: "#faqs" }
      ]
    },
    {
      title: "Legal",
      links: [
        { name: "Privacy Policy", href: "#faqs" },
        { name: "Terms & Conditions", href: "#faqs" },
        { name: "Security Audits", href: "#faqs" },
        { name: "ESG Policy", href: "#faqs" }
      ]
    }
  ];

  return (
    <footer className="bg-slate-50 border-t border-slate-200">
      
      {/* Links matrix block */}
      <div className="max-w-7xl mx-auto px-6 md:px-12 py-16">
        <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-8">
          
          {/* Brand block (2 cols wide on desktop) */}
          <div className="col-span-2 lg:col-span-1 space-y-4">
            <div className="flex items-center gap-1.5">
              <span className="font-sans text-2xl font-black tracking-tight text-brand-primary leading-none">
                Checkout<span className="text-brand-electric">Pay</span>
              </span>
            </div>
            <p className="text-xs text-slate-500 leading-relaxed font-semibold">
              Intelligent Payment Gateway. Engineered for high-conversion commerce in Nigeria. Powered in licensing partnership with METRAVON INNOVATION LTD.
            </p>
            
            {/* Social flags */}
            <div className="flex gap-4 pt-1">
              <Globe className="w-4 h-4 text-slate-400 hover:text-brand-primary cursor-pointer transition-colors" />
              <ShieldCheck className="w-4 h-4 text-slate-400 hover:text-brand-primary cursor-pointer transition-colors" />
            </div>
          </div>

          {/* Matrix Columns */}
          {footerSections.map((sect, sIdx) => (
            <div key={sIdx} className="space-y-4">
              <h5 className="text-[10px] font-extrabold text-midnight-deep uppercase tracking-widest">
                {sect.title}
              </h5>
              <ul className="space-y-2.5 text-xs font-semibold">
                {sect.links.map((lnk, lIdx) => (
                  <li key={lIdx}>
                    <a 
                      href={lnk.href} 
                      className="text-slate-500 hover:text-brand-electric transition-colors"
                    >
                      {lnk.name}
                    </a>
                  </li>
                ))}
              </ul>
            </div>
          ))}

        </div>
      </div>

      {/* Extreme Bottom Credits & Badges bar */}
      <div className="bg-slate-100/65 border-t border-slate-200/50">
        <div className="max-w-7xl mx-auto px-6 md:px-12 py-8 flex flex-col md:flex-row justify-between items-center gap-6">
          
          {/* Copyright description */}
          <div className="text-center md:text-left space-y-1 text-[11px] font-semibold text-slate-500 max-w-xl">
            <p>© {currentYear} CheckoutPay. All Rights Reserved.</p>
            <p>
              In partnership with Metavon Innovation Ltd. Licensed by the CBN (Central Bank of Nigeria). Integrations validated under global PCI-DSS Criteria.
            </p>
          </div>

          {/* Secure Icons badge */}
          <div className="flex flex-wrap gap-4 justify-center items-center text-[10px] font-extrabold text-slate-600 uppercase tracking-wider">
            <span className="flex items-center gap-1 bg-white px-3 py-1.5 rounded-lg border border-slate-200">
              <ShieldCheck className="w-4 h-4 text-[#22C55E]" /> PCI DSS Compliant
            </span>
            <span className="flex items-center gap-1 bg-white px-3 py-1.5 rounded-lg border border-slate-200">
              <Lock className="w-4 h-4 text-brand-electric" /> AES Encrypted
            </span>
          </div>

        </div>
      </div>

    </footer>
  );
}
