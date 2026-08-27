import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

const main = readFileSync(new URL('./main.tsx', import.meta.url), 'utf8');
const workspace = readFileSync(new URL('./accommodation-enterprise.tsx', import.meta.url), 'utf8');
const propertiesWorkspace = readFileSync(new URL('./accommodation-properties.tsx', import.meta.url), 'utf8');
const vendorPortal = readFileSync(new URL('../../php_app/vendor/portal.php', import.meta.url), 'utf8');
const dashboardShell = readFileSync(new URL('../../php_app/includes/dashboard_shell.php', import.meta.url), 'utf8');
const serviceDashboard = readFileSync(new URL('../../php_app/vendor/service-dashboard.php', import.meta.url), 'utf8');
const retiredPage = readFileSync(new URL('../../php_app/vendor/accommodation-os.php', import.meta.url), 'utf8');
const retiredApi = readFileSync(new URL('../../php_app/api/tie/vendor/accommodation.php', import.meta.url), 'utf8');
const retiredOperationsApi = readFileSync(new URL('../../php_app/api/tie/vendor/accommodation-operations.php', import.meta.url), 'utf8');

describe('Enterprise accommodation cutover contract', () => {
  it('routes the accommodation workspace to the v2 React implementation', () => {
    expect(main).toContain('<EnterpriseAccommodationDashboard boot={boot} />');
  });

  it('uses only versioned accommodation v2 APIs', () => {
    expect(workspace).toContain('api/tie/vendor/accommodation/');
    expect(workspace).not.toContain("vendor/accommodation.php");
    expect(workspace).not.toContain('accommodation-operations.php');
    expect(main).not.toContain('function AccommodationDashboard');
    expect(main).not.toContain('function AccommodationOperations');
    expect(main).not.toContain("import './accommodation-dashboard.css'");
    expect(main).not.toContain("import './accommodation-os.css'");
  });

  it('routes every first-party accommodation entry to the React workspace', () => {
    for (const source of [vendorPortal, dashboardShell, serviceDashboard]) {
      expect(source).toContain('ai.php#/accommodation');
      expect(source).not.toContain('vendor/accommodation-os.php');
    }
    expect(retiredPage).toContain("requireApprovedVendor();");
    expect(retiredPage).toContain("ai.php#/accommodation");
    expect(retiredPage).not.toContain('demo_hotel_vendor');
  });

  it('fails stale v1 API callers closed with an explicit retirement response', () => {
    for (const source of [retiredApi, retiredOperationsApi]) {
      expect(source).toContain('http_response_code(410)');
      expect(source).toContain("api/tie/vendor/accommodation/");
    }
  });

  it('keeps nightly optimistic concurrency and explicit publication visible', () => {
    expect(workspace).toContain('version:selectedNight?.version??form.version');
    expect(workspace).toContain("onLifecycle('submit_review')");
    expect(workspace).toContain('Nothing becomes public until review and activation.');
  });

  it('uses the governed multi-property workspace instead of the old properties form', () => {
    expect(workspace).toContain('<AccommodationPropertiesWorkspace');
    expect(propertiesWorkspace).toContain("'properties.php'");
    expect(propertiesWorkspace).toContain('active_property_id');
    expect(propertiesWorkspace).toContain('PRIVATE PROPERTY SETUP');
    expect(propertiesWorkspace).toContain('Submit for review');
    expect(propertiesWorkspace).toContain('Nothing becomes public until review is accepted.');
  });
});
