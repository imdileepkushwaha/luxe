import Config from '@/constants/Config';

export type ProductColor = {
  name: string;
  hex: string;
  product_id: number;
};

export type ProductReview = {
  customer_name: string;
  rating: number;
  review_text: string;
  seller_response?: string;
  created_at: string;
};

export type Product = {
  id: number;
  name: string;
  slug?: string;
  brand: string;
  category: string;
  price: number;
  original_price: number;
  rating: number;
  review_count: number;
  badge: string;
  image_url: string;
  images: string[];
  description?: string;
  sizes: string[];
  colors: string[];
  color_swatches?: ProductColor[];
  reviews?: ProductReview[];
  offer_flash_text?: string;
  offer_bank_text?: string;
  offer_countdown_seconds?: number;
  discount_percent?: number;
};

export class ApiError extends Error {
  constructor(message: string) {
    super(message);
    this.name = 'ApiError';
  }
}

async function getJson<T>(path: string): Promise<T> {
  const url = path.startsWith('http') ? path : `${Config.API_URL}/${path.replace(/^\//, '')}`;
  let res: Response;
  try {
    res = await fetch(url);
  } catch {
    throw new ApiError('Cannot reach the store. Is the PHP server running on port 5555?');
  }
  let data: any = null;
  try {
    data = await res.json();
  } catch {
    throw new ApiError(`Server error (${res.status})`);
  }
  if (!res.ok || data?.ok === false) {
    throw new ApiError(data?.error || `Server error (${res.status})`);
  }
  return data as T;
}

export async function fetchProducts(opts?: {
  limit?: number;
  search?: string;
  category?: string;
  id?: number | string;
  offers?: boolean;
}): Promise<{ products: Product[]; product: Product | null }> {
  const params = new URLSearchParams();
  if (opts?.limit) params.set('limit', String(opts.limit));
  if (opts?.search) params.set('search', opts.search);
  if (opts?.category && opts.category !== 'All') params.set('category', opts.category);
  if (opts?.id) params.set('id', String(opts.id));
  if (opts?.offers) params.set('offers', '1');
  const q = params.toString();
  const data = await getJson<{ products: Product[]; product?: Product | null }>(
    `products.php${q ? `?${q}` : ''}`
  );
  return {
    products: data.products || [],
    product: data.product ?? data.products?.[0] ?? null,
  };
}

export type UserAddress = {
  id: number;
  type: string;
  name: string;
  phone: string;
  line1: string;
  line2: string;
  city: string;
  state: string;
  pin: string;
  isDefault: boolean;
};

export async function fetchCategories(): Promise<string[]> {
  const data = await getJson<{ categories: string[] }>('categories.php');
  return data.categories?.length ? data.categories : ['All'];
}

export async function fetchAddresses(userId: number): Promise<UserAddress[]> {
  const data = await getJson<{ addresses: UserAddress[] }>(
    `mobile_addresses.php?user_id=${encodeURIComponent(String(userId))}`
  );
  return Array.isArray(data.addresses) ? data.addresses : [];
}

export function formatAddressLines(addr: UserAddress): string {
  const street = [addr.line1, addr.line2].filter((p) => p && p.trim()).join(', ');
  const city = [addr.city, addr.state].filter(Boolean).join(', ');
  const pin = addr.pin ? ` — ${addr.pin}` : '';
  return [street, city ? `${city}${pin}` : ''].filter(Boolean).join('\n');
}

export type OrderReturnRequest = {
  id: number;
  status: string;
  pickup_status: string;
  reason: string;
  details: string;
  refund_amount: number;
  refund_mode: string;
};

export type OrderItem = {
  id: number;
  product_id: number;
  name: string;
  qty: number;
  price: number;
  variant: string;
  status: string;
  image_url: string;
  can_review?: boolean;
  return_request?: OrderReturnRequest | null;
};

export type OrderCancelRequest = {
  status: string;
  reason: string;
  seller_reason: string;
};

export type Order = {
  id: number;
  order_ref: string;
  status: string;
  total_amount: number;
  payment_method: string;
  shipping_address: string;
  created_at: string;
  item_count: number;
  items: OrderItem[];
  can_cancel?: boolean;
  can_return?: boolean;
  can_invoice?: boolean;
  cancel_request?: OrderCancelRequest | null;
  loyalty_points?: number;
  loyalty_status?: 'credited' | 'pending' | 'upcoming' | 'none';
};

export type ProfileStats = {
  order_count: number;
  lifetime_spend_rupees: number;
  total_saved_rupees: number;
  delivered_count: number;
  pending_count: number;
  cancelled_count: number;
};

export type LoyaltyHistoryItem = {
  type: string;
  pts: number;
  ref: string;
  label: string;
  date: string;
};

export type ProfileReview = {
  product_id: number;
  name: string;
  order_ref: string;
  variant: string;
  image_url: string;
  review_id: number;
  rating: number;
  review_text: string;
  review_status: string;
  seller_response: string;
  can_review: boolean;
};

export type ProfileSummary = {
  stats: ProfileStats;
  loyalty: {
    balance: number;
    earned: number;
    pending: number;
    redeemed: number;
    gold_at: number;
    platinum_at: number;
    tier: {
      title: string;
      lead: string;
      progress: number;
      next: string | null;
      to_next: number;
    };
    history: LoyaltyHistoryItem[];
  };
  reviews: ProfileReview[];
  user: {
    member_since?: string;
    phone?: string;
  };
};

export async function fetchProfileSummary(userId: number): Promise<ProfileSummary> {
  const data = await getJson<ProfileSummary & { ok?: boolean }>(
    `mobile_profile.php?user_id=${encodeURIComponent(String(userId))}`
  );
  return data;
}

export async function fetchOrders(userId: number): Promise<Order[]> {
  const data = await getJson<{ orders: Order[] }>(
    `mobile_orders.php?user_id=${encodeURIComponent(String(userId))}`
  );
  return Array.isArray(data.orders) ? data.orders : [];
}

export function formatPrice(value?: number | null): string {
  const n = Number(value || 0);
  return `₹${n.toLocaleString('en-IN')}`;
}

export function categoryLabel(value: string): string {
  if (!value || value === 'All') return 'All';
  return value.replace(/[-_]/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}
