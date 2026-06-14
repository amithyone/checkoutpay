---
name: Premium Nigerian Fintech
colors:
  surface: '#f8f9fe'
  surface-dim: '#d8dadf'
  surface-bright: '#f8f9fe'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f2f3f8'
  surface-container: '#eceef3'
  surface-container-high: '#e7e8ed'
  surface-container-highest: '#e1e2e7'
  on-surface: '#191c1f'
  on-surface-variant: '#454655'
  inverse-surface: '#2e3134'
  inverse-on-surface: '#eff0f5'
  outline: '#757687'
  outline-variant: '#c5c5d8'
  surface-tint: '#3748e7'
  primary: '#001bca'
  on-primary: '#ffffff'
  primary-container: '#2d3fe0'
  on-primary-container: '#c5c9ff'
  inverse-primary: '#bdc2ff'
  secondary: '#3045e6'
  on-secondary: '#ffffff'
  secondary-container: '#4e62ff'
  on-secondary-container: '#fffbff'
  tertiary: '#5200b5'
  on-tertiary: '#ffffff'
  tertiary-container: '#6c22dd'
  on-tertiary-container: '#d8c4ff'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#dfe0ff'
  primary-fixed-dim: '#bdc2ff'
  on-primary-fixed: '#000865'
  on-primary-fixed-variant: '#1328d1'
  secondary-fixed: '#dfe0ff'
  secondary-fixed-dim: '#bcc2ff'
  on-secondary-fixed: '#000a63'
  on-secondary-fixed-variant: '#0c28d2'
  tertiary-fixed: '#eaddff'
  tertiary-fixed-dim: '#d2bbff'
  on-tertiary-fixed: '#25005a'
  on-tertiary-fixed-variant: '#5a00c6'
  background: '#f8f9fe'
  on-background: '#191c1f'
  surface-variant: '#e1e2e7'
  success-green: '#22C55E'
  text-slate: '#475569'
  midnight-deep: '#111827'
typography:
  display-lg:
    fontFamily: Hanken Grotesk
    fontSize: 48px
    fontWeight: '800'
    lineHeight: 56px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Hanken Grotesk
    fontSize: 32px
    fontWeight: '800'
    lineHeight: 40px
    letterSpacing: -0.01em
  headline-lg-mobile:
    fontFamily: Hanken Grotesk
    fontSize: 24px
    fontWeight: '800'
    lineHeight: 32px
  headline-md:
    fontFamily: Hanken Grotesk
    fontSize: 24px
    fontWeight: '700'
    lineHeight: 32px
  body-lg:
    fontFamily: Hanken Grotesk
    fontSize: 18px
    fontWeight: '500'
    lineHeight: 28px
  body-md:
    fontFamily: Hanken Grotesk
    fontSize: 16px
    fontWeight: '500'
    lineHeight: 24px
  label-sm:
    fontFamily: Hanken Grotesk
    fontSize: 14px
    fontWeight: '600'
    lineHeight: 20px
    letterSpacing: 0.01em
  label-xs:
    fontFamily: Hanken Grotesk
    fontSize: 12px
    fontWeight: '700'
    lineHeight: 16px
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  container-max: 1280px
  gutter: 24px
  margin-mobile: 16px
  margin-desktop: 32px
  stack-sm: 8px
  stack-md: 16px
  stack-lg: 32px
---

## Brand & Style

The design system is engineered for a "Fintech 2.0" aesthetic, specifically tailored for the high-growth Nigerian market. The brand personality is **commanding, secure, and frictionless**, positioning the product as a premier infrastructure for modern commerce.

The visual style is a hybrid of **Modern Corporate** and **Soft Glassmorphism**. It utilizes a sophisticated "Midnight Indigo" foundation to evoke institutional trust, balanced by "Electric Blue" interactive elements that signify speed and innovation. The interface prioritizes clarity and status, ensuring that whether a user is managing a WhatsApp Wallet or processing Payouts, the experience feels premium and enterprise-grade.

## Colors

The palette is anchored by **Midnight Indigo (#2D3FE0)**, used for primary brand moments and key navigation. **Electric Blue (#4D61FF)** serves as the high-energy interactive color for buttons, links, and active states, providing a vibrant contrast that guides the user's eye.

Backgrounds utilize a crisp, off-white **#F9FAFF** to maintain a clean "Modern Fintech" feel, distinct from generic pure white. Text hierarchy is managed through **Midnight Deep (#111827)** for headers and **Slate Gray (#475569)** for secondary body copy, ensuring optimal legibility while maintaining a sophisticated tonal range. Success states (crucial for payment confirmations) use a vivid **#22C55E**.

## Typography

This design system exclusively uses **Hanken Grotesk** to leverage its sharp, contemporary geometry. The hierarchy is driven by extreme weight contrast:
- **Headings:** Use 'Extra Bold' (800) for high-impact titles (Invoices, Tickets, Rentals) to project authority and strength.
- **Body Text:** Use 'Medium' (500) as the baseline weight. This provides more visual "weight" than standard regular fonts, ensuring text doesn't wash out on high-resolution mobile screens common in the Nigerian tech ecosystem.
- **Labels:** Use 'SemiBold' or 'Bold' (600-700) at smaller sizes for functional UI elements like the "1% + ₦50" pricing callouts.

## Layout & Spacing

The system follows a **12-column fluid grid** for desktop and a **single-column fluid layout** for mobile. Spacing is intentional and generous, moving away from cluttered "legacy" bank interfaces.

A base **8px spacing scale** governs the layout. Margins are set at **32px for desktop** to provide breathing room, while mobile margins scale down to **16px** to maximize real estate for transactional data. Large vertical gaps (Stack LG) should be used between major product sections (e.g., separating "Online Payments" from "Memberships") to maintain a sense of premium minimalism.

## Elevation & Depth

Visual depth is achieved through **Soft Tonal Layering** and **Glassmorphism**.
- **Surface Layers:** The primary background is #F9FAFF. Cards and containers sit on top of this with a pure white (#FFFFFF) fill and a subtle, high-diffusion shadow (0px 10px 30px rgba(45, 63, 224, 0.05)).
- **Glassmorphism:** For top navigation bars and secondary modals, use a backdrop blur (12px) with a semi-transparent white tint (rgba(255, 255, 255, 0.7)). This creates an "Ultra-Modern" feel that suggests transparency and technical sophistication.
- **Interactive Depth:** On hover or active state, elements should slightly lift using a more pronounced shadow rather than changing the border color.

## Shapes

The design system adheres to a strict **12px (0.75rem)** corner radius for all standard UI components, including cards, input fields, and buttons. This "ROUND_TWELVE" approach strikes the perfect balance between the approachability of rounded corners and the professional structure of sharp edges.

- **Small elements (Checkboxes):** 4px radius.
- **Medium elements (Buttons, Inputs):** 12px radius.
- **Large elements (Containers, Modals):** 24px (rounded-xl) for a softer, more premium aesthetic.

## Components

- **Buttons:** Primary buttons use the Electric Blue (#4D61FF) background with white Hanken Grotesk Medium text. They should have a 12px radius and 16px vertical padding for a "heavier" feel.
- **Input Fields:** Use a subtle Slate Gray border (1px solid) that transforms into a 2px Midnight Indigo border on focus. Include a 4px glow (Electric Blue at 10% opacity) for a high-tech active state.
- **Cards:** Cards for product categories (e.g., "WhatsApp Wallet") should feature high-contrast icons, generous 32px internal padding, and the signature 12px roundedness.
- **Chips/Badges:** For status (e.g., "Paid", "Pending"), use low-saturation background tints with high-saturation text of the same hue (e.g., Success Green tint with Success Green text).
- **Glass Containers:** Use for sidebars or "Payouts" dashboard widgets to maintain the "Fintech 2.0" aesthetic—1px semi-transparent borders with a 12px blur effect.
- **Pricing Callouts:** Specifically for the "1% + ₦50" model, use a distinct badge component with a Midnight Indigo background and bold white typography to ensure it is the most prominent piece of information in the conversion funnel.