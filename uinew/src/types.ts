/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

export interface FAQItem {
  id: string;
  question: string;
  answer: string;
}

export interface PricingExample {
  amount: number;
  fee: number;
}

export interface ChatMessage {
  id: string;
  sender: 'bot' | 'user' | 'system';
  text: string;
  timestamp?: string;
  status?: 'sending' | 'sent' | 'delivered' | 'read' | 'success' | 'failed';
}

export interface ProductItem {
  id: string;
  title: string;
  description: string;
  iconName: string;
  tags?: string[];
  imageUrl?: string;
}
