import { describe, expect, it } from 'vitest';
import { formatAdminCurrency, sectionDescription } from './admin-control-center';

describe('Admin Control Center presentation rules', () => {
  it('keeps unavailable financial evidence explicit', () => {
    expect(formatAdminCurrency(null)).toBe('Unavailable');
  });

  it('formats verified Malawi Kwacha values without inventing decimals', () => {
    expect(formatAdminCurrency(4820000)).toContain('4,820,000');
  });

  it('describes the operational payment boundary', () => {
    expect(sectionDescription('payments')).toContain('reconciliation');
  });
});
