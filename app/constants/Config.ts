/**
 * PHP storefront + mobile API origin.
 * Override locally: EXPO_PUBLIC_SITE_ORIGIN=http://localhost:5555
 */
const SITE_ORIGIN =
  process.env.EXPO_PUBLIC_SITE_ORIGIN || 'https://ecommerce.softflipsolutions.com';

export default {
  SITE_ORIGIN,
  API_URL: `${SITE_ORIGIN}/api`,
};

export function mediaUrl(path?: string | null): string {
  if (!path) return '';
  if (/^https?:\/\//i.test(path)) return path;
  return `${SITE_ORIGIN}/${String(path).replace(/^\/+/, '')}`;
}
