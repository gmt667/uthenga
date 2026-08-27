import { ChangeEvent, useEffect, useMemo, useState } from 'react';
import { Archive, Building2, CheckCircle2, ChevronRight, Copy, Eye, FileText, MapPin, Plus, Search, Settings, ShieldCheck, Upload, X } from 'lucide-react';
import './accommodation-properties.css';

type Boot = { csrf_token?: string };
type Property = any;

const base = String((window as any).UTHENGA_BASE_URL || '/uthenga/').replace(/\/?$/, '/');
const endpoint = (path: string) => `${base}api/tie/vendor/accommodation/${path}`;

async function json<T>(path: string, method = 'GET', body?: any, csrf?: string): Promise<T> {
  const response = await fetch(endpoint(path), { method, credentials: 'include', headers: { 'Content-Type': 'application/json', ...(csrf ? { 'X-CSRF-Token': csrf } : {}) }, body: body === undefined ? undefined : JSON.stringify(body) });
  const data = await response.json().catch(() => ({}));
  if (!response.ok || !data.success) throw new Error(data?.error?.message || 'This property operation could not be completed.');
  return data as T;
}

async function upload(path: string, data: FormData, csrf?: string): Promise<any> {
  const response = await fetch(endpoint(path), { method: 'POST', credentials: 'include', headers: csrf ? { 'X-CSRF-Token': csrf } : {}, body: data });
  const result = await response.json().catch(() => ({}));
  if (!response.ok || !result.success) throw new Error(result?.error?.message || 'The upload could not be completed.');
  return result;
}

const label = (value: string) => String(value || '').replaceAll('_', ' ').toLowerCase().replace(/\b\w/g, c => c.toUpperCase());
const stateClass = (status: string) => `acc-properties__state is-${String(status).toLowerCase()}`;

export function AccommodationPropertiesWorkspace({ boot, selectedId, onManage, onChanged }: { boot: Boot; selectedId: string; onManage: (id: string) => void; onChanged: () => Promise<void> }) {
  const [workspace, setWorkspace] = useState<any>(null);
  const [search, setSearch] = useState('');
  const [filter, setFilter] = useState('ALL');
  const [notice, setNotice] = useState('');
  const [busy, setBusy] = useState<string>('');
  const [wizard, setWizard] = useState<{ propertyId?: string; step: number } | null>(null);

  const load = async () => {
    const data = await json<any>('properties.php');
    setWorkspace(data.portfolio);
  };
  useEffect(() => { void load().catch(e => setNotice(e.message)); }, []);
  const properties = useMemo(() => (workspace?.properties || []).filter((p: Property) => {
    const matchesText = [p.name, p.city, p.address, p.property_type].join(' ').toLowerCase().includes(search.toLowerCase());
    const matchesState = filter === 'ALL' || p.status === filter;
    return matchesText && matchesState;
  }), [workspace, search, filter]);
  const synchronise = async () => { await Promise.all([load(), onChanged()]); };
  const action = async (property: Property, actionName: string) => {
    setBusy(`${actionName}:${property.id}`); setNotice('');
    try {
      if (actionName === 'activate_context' || actionName === 'duplicate') await json('properties.php', 'POST', { action: actionName, property_id: property.id }, boot.csrf_token);
      else await json('property.php', 'POST', { action: actionName, property_id: property.id }, boot.csrf_token);
      await synchronise();
      setNotice(actionName === 'duplicate' ? 'A separate private draft was created. It has no public inventory.' : 'Property state updated from the authoritative lifecycle.');
    } catch (e) { setNotice(e instanceof Error ? e.message : 'The property operation failed.'); }
    finally { setBusy(''); }
  };
  const startCreate = () => setWizard({ step: 0 });
  return <section className="acc-properties">
    <header className="acc-properties__header"><div><small>ACCOMMODATION PORTFOLIO</small><h1>Properties</h1><p>One selected management context. Publication remains a separate, reviewed release.</p></div><button className="acc-properties__primary" onClick={startCreate}><Plus />Create property</button></header>
    {notice && <div className="acc-properties__notice"><span>{notice}</span><button onClick={() => setNotice('')} aria-label="Dismiss"><X /></button></div>}
    <div className="acc-properties__summary">
      <Summary value={workspace?.summary?.properties ?? '—'} label="Properties" />
      <Summary value={workspace?.summary?.published ?? '—'} label="Published" tone="good" />
      <Summary value={workspace?.summary?.drafts ?? '—'} label="Private drafts" tone="muted" />
      <Summary value={workspace?.summary?.needs_action ?? '—'} label="Needs action" tone="warn" />
    </div>
    <div className="acc-properties__tools"><label><Search /><input value={search} onChange={e => setSearch(e.target.value)} placeholder="Search properties…" /></label><select value={filter} onChange={e => setFilter(e.target.value)}><option value="ALL">All lifecycle states</option>{['PRIVATE_DRAFT', 'SETUP_INCOMPLETE', 'READY_FOR_REVIEW', 'PUBLISHED', 'ACTIVE', 'PAUSED', 'ARCHIVED'].map((s: string) => <option value={s} key={s}>{label(s)}</option>)}</select></div>
    <div className="acc-properties__grid">{properties.map((property: Property) => <PropertyTile key={property.id} property={property} active={property.id === workspace?.active_property_id} selected={property.id === selectedId} busy={busy} onManage={() => onManage(property.id)} onAction={action} onSetup={() => setWizard({ propertyId: property.id, step: 0 })} />)}</div>
    {!properties.length && <div className="acc-properties__empty"><Building2 /><h2>No matching properties</h2><p>There are no portfolio records matching this search. A new property begins privately and cannot be booked until its setup and review are complete.</p></div>}
    {wizard && <PropertyWizard boot={boot} propertyId={wizard.propertyId} step={wizard.step} canonical={workspace?.canonical_values} onClose={() => setWizard(null)} onReady={async (id: string) => { await synchronise(); setWizard(null); onManage(id); }} />}
  </section>;
}

function Summary({ value, label: text, tone = '' }: { value: any; label: string; tone?: string }) { return <article className={`acc-properties__summary-card ${tone}`}><b>{value}</b><span>{text}</span></article>; }

function PropertyTile({ property, active, selected, busy, onManage, onAction, onSetup }: any) {
  const readiness = property.readiness || { percent: 0, checks: [] };
  const metrics = property.metrics || {};
  const waiting = (name: string) => busy === `${name}:${property.id}`;
  const publishable = ['PRIVATE_DRAFT', 'SETUP_INCOMPLETE'].includes(property.status);
  return <article className={`acc-properties__tile ${active ? 'is-active' : ''} ${selected ? 'is-selected' : ''}`}>
    <div className="acc-properties__cover">{property.media?.find?.((m: any) => m.is_cover)?.url || property.image_url ? <img src={property.media?.find?.((m: any) => m.is_cover)?.url || property.image_url} alt="" /> : <Building2 />}<div className="acc-properties__badges">{active && <span className="active">Active context</span>}<span className={stateClass(property.status)}>{label(property.status)}</span></div></div>
    <div className="acc-properties__tile-body"><h2>{property.name}</h2><p><MapPin />{[property.locality, property.city, property.region].filter(Boolean).join(', ') || property.address || 'Location setup pending'}</p><div className="acc-properties__facts"><span>{metrics.rooms ?? 0} rooms</span><span>{metrics.reservations ?? 0} reservations</span><span>{metrics.nightly_rows ?? 0} ledger nights</span></div><div className="acc-properties__readiness"><span>Setup readiness <b>{readiness.percent ?? 0}%</b></span><i><em style={{ width: `${readiness.percent ?? 0}%` }} /></i><small>{readiness.ready_for_review ? 'All deterministic publication checks are ready.' : `${(readiness.checks || []).filter((c: any) => !c.complete).map((c: any) => c.label).join(', ') || 'Continue setup'}`}</small></div></div>
    <footer><button className="manage" onClick={onManage}>Manage <ChevronRight /></button>{!active && property.status !== 'ARCHIVED' && <button disabled={!!busy} onClick={() => onAction(property, 'activate_context')}>Set active</button>}<button onClick={onSetup}><Settings />Setup</button>{['PUBLISHED', 'ACTIVE'].includes(property.status) && property.listing_id && <a href={`${base}shop.php?listing=${encodeURIComponent(property.listing_id)}`} target="_blank" rel="noreferrer"><Eye />Preview</a>}<details><summary>More</summary><div>{publishable && <button disabled={!!busy} onClick={() => onAction(property, 'submit_review')}> <ShieldCheck />{waiting('submit_review') ? 'Checking…' : 'Submit review'}</button>}{['PUBLISHED', 'PAUSED'].includes(property.status) && <button disabled={!!busy} onClick={() => onAction(property, 'activate')}> <CheckCircle2 />Activate</button>}{['ACTIVE', 'PUBLISHED'].includes(property.status) && <button disabled={!!busy} onClick={() => onAction(property, 'pause')}>Pause</button>}{property.status !== 'ARCHIVED' && <button disabled={!!busy} onClick={() => onAction(property, 'duplicate')}><Copy />Duplicate draft</button>}{['PRIVATE_DRAFT', 'SETUP_INCOMPLETE', 'PUBLISHED', 'PAUSED'].includes(property.status) && <button className="danger" disabled={!!busy} onClick={() => onAction(property, 'archive')}><Archive />Archive</button>}</div></details></footer>
  </article>;
}

function PropertyWizard({ boot, propertyId: incomingId, step: startStep, canonical, onClose, onReady }: any) {
  const [propertyId, setPropertyId] = useState<string | undefined>(incomingId);
  const [step, setStep] = useState(startStep || 0);
  const [property, setProperty] = useState<any>(null);
  const [form, setForm] = useState<any>({ name: '', property_type: 'HOTEL', address: '', city: '', description: '', display_name: '', short_description: '', latitude: '', longitude: '', location_source: 'MAP_PIN', quality_classification: 'UNRATED', amenities: [], highlights: [], guest_policy: { children_allowed: false, pets_allowed: false, smoking_allowed: false, events_allowed: false, visitors_allowed: false, quiet_hours_from: '22:00', quiet_hours_to: '06:00' }, check_in_time: '14:00', check_out_time: '10:00', legal_business_name: '', trading_name: '', business_registration: '', tax_identifier: '', website_url: '' });
  const [busy, setBusy] = useState(false); const [error, setError] = useState(''); const [assets, setAssets] = useState<any>({ media: [], documents: [] });
  const steps = ['Identity', 'Type', 'Location', 'Description', 'Media', 'Facilities', 'Policies', 'Business', 'Review'];
  const getDetail = async (id: string) => { const data = await json<any>(`property-profile.php?property_id=${encodeURIComponent(id)}`); const p = data.detail.property; setProperty(p); setForm((old: any) => ({ ...old, ...p, guest_policy: p.guest_policy || old.guest_policy, amenities: p.amenities || [], highlights: p.highlights || [] })); const media = await json<any>(`property-media.php?property_id=${encodeURIComponent(id)}`); setAssets(media.assets); };
  useEffect(() => { if (propertyId) void getDetail(propertyId).catch(e => setError(e.message)); }, [propertyId]);
  const set = (key: string, value: any) => setForm((current: any) => ({ ...current, [key]: value }));
  const create = async () => { setBusy(true); setError(''); try { const data = await json<any>('portfolio.php', 'POST', { action: 'create_property', name: form.name, property_type: form.property_type, address: '' }, boot.csrf_token); setPropertyId(data.property.id); setStep(1); } catch (e) { setError(e instanceof Error ? e.message : 'Unable to create the private draft.'); } finally { setBusy(false); } };
  const save = async () => { if (!propertyId || !property) return; setBusy(true); setError(''); try { const data = await json<any>('property-profile.php', 'POST', { property_id: propertyId, profile_version: property.profile_version || property.version, ...form }, boot.csrf_token); setProperty(data.property); setForm((old: any) => ({ ...old, ...data.property })); } catch (e) { setError(e instanceof Error ? e.message : 'The private draft could not be saved.'); throw e; } finally { setBusy(false); } };
  const next = async () => { if (!propertyId) return create(); try { await save(); setStep((s: number) => Math.min(8, s + 1)); } catch { /* error already visible */ } };
  const pickFile = async (event: ChangeEvent<HTMLInputElement>, kind: 'media' | 'document') => { const file = event.target.files?.[0]; if (!file || !propertyId) return; setBusy(true); setError(''); try { const data = new FormData(); data.set('property_id', propertyId); data.set('action', 'upload'); data.set('file', file); if (kind === 'media') { data.set('media_category', 'EXTERIOR'); data.set('alt_text', form.name || 'Property image'); await upload('property-media.php', data, boot.csrf_token); } else { data.set('category', 'BUSINESS_REGISTRATION'); await upload('property-document.php', data, boot.csrf_token); } await getDetail(propertyId); } catch (e) { setError(e instanceof Error ? e.message : 'Upload failed.'); } finally { setBusy(false); event.target.value = ''; } };
  const toggle = (field: 'amenities' | 'highlights', value: string) => set(field, form[field].includes(value) ? form[field].filter((v: string) => v !== value) : [...form[field], value]);
  const review = property?.readiness;
  return <div className="acc-properties__wizard-backdrop"><section className="acc-properties__wizard"><header><div><small>PRIVATE PROPERTY SETUP</small><h2>{propertyId ? form.name || 'Accommodation property' : 'Create your property'}</h2><p>Every step saves to the draft. Nothing becomes public until review is accepted.</p></div><button onClick={onClose}><X /></button></header><ol>{steps.map((name, index) => <li key={name} className={index === step ? 'active' : index < step ? 'done' : ''}><span>{index + 1}</span>{name}</li>)}</ol>{error && <p className="acc-properties__error">{error}</p>}<main>
    {step === 0 && <Fields form={form} set={set} keys={[['name', 'Property name', 'text'], ['property_type', 'Property type', 'select']]} types={canonical?.property_types || ['HOTEL', 'LODGE', 'GUESTHOUSE', 'HOSTEL', 'SERVICED_APARTMENT']} />}
    {step === 1 && <Fields form={form} set={set} keys={[['property_type', 'Property type', 'select'], ['quality_classification', 'Self-declared classification', 'select']]} types={canonical?.property_types || ['HOTEL', 'LODGE', 'GUESTHOUSE', 'HOSTEL', 'SERVICED_APARTMENT']} qualities />}
    {step === 2 && <><Fields form={form} set={set} keys={[['address', 'Street address', 'text'], ['city', 'City', 'text'], ['region', 'Region', 'text'], ['district', 'District', 'text'], ['locality', 'Locality / area', 'text'], ['latitude', 'Latitude', 'number'], ['longitude', 'Longitude', 'number']]} /><p className="acc-properties__hint"><MapPin /> Pin or copy verified coordinates from your map. Both coordinates are required before review.</p></>}
    {step === 3 && <Fields form={form} set={set} keys={[['display_name', 'Customer-facing name', 'text'], ['short_description', 'Short description', 'textarea'], ['description', 'Full property description', 'textarea'], ['phone', 'Operations phone', 'tel'], ['email', 'Operations email', 'email'], ['check_in_time', 'Check-in time', 'time'], ['check_out_time', 'Check-out time', 'time']]} />}
    {step === 4 && <UploadPanel label="Property media" accept="image/jpeg,image/png,image/webp" files={assets.media || []} onPick={(e: ChangeEvent<HTMLInputElement>) => void pickFile(e, 'media')} icon={<Upload />} />}
    {step === 5 && <ChoiceGrid title="Amenities" values={canonical?.amenities || []} selected={form.amenities} onToggle={(v: string) => toggle('amenities', v)} extra={<ChoiceGrid title="Highlights" values={canonical?.highlights || []} selected={form.highlights} onToggle={(v: string) => toggle('highlights', v)} />} />}
    {step === 6 && <PolicyForm form={form} set={set} />}
    {step === 7 && <><Fields form={form} set={set} keys={[['legal_business_name', 'Legal business name', 'text'], ['trading_name', 'Trading name', 'text'], ['business_registration', 'Business registration number', 'text'], ['tax_identifier', 'Tax identifier', 'text'], ['website_url', 'Website URL', 'url']]} /><UploadPanel label="Verification document" accept="application/pdf,image/jpeg,image/png" files={assets.documents || []} onPick={(e: ChangeEvent<HTMLInputElement>) => void pickFile(e, 'document')} icon={<FileText />} /></>}
    {step === 8 && <div className="acc-properties__review"><h3>Publication readiness</h3><p>Review is deterministic. It never publishes this property automatically.</p>{(review?.checks || []).map((check: any) => <p key={check.key} className={check.complete ? 'done' : 'missing'}>{check.complete ? <CheckCircle2 /> : <X />}{check.label}</p>)}{review?.ready_for_review ? <p className="ready">This draft can be submitted for administrator review.</p> : <p>Complete each missing requirement, plus rooms and pricing, before submission.</p>}</div>}
  </main><footer><button onClick={() => step > 0 ? setStep(step - 1) : onClose()} disabled={busy}>Back</button>{step < 8 ? <button className="primary" onClick={() => void next()} disabled={busy || (step === 0 && !form.name)}>{busy ? 'Saving…' : propertyId ? 'Save & continue' : 'Create private draft'}</button> : <><button onClick={() => propertyId && void save()} disabled={busy}>Save draft</button><button className="primary" disabled={busy || !review?.ready_for_review} onClick={async () => { if (!propertyId) return; setBusy(true); try { await json('property.php', 'POST', { property_id: propertyId, action: 'submit_review' }, boot.csrf_token); await onReady(propertyId); } catch (e) { setError(e instanceof Error ? e.message : 'Could not submit for review.'); } finally { setBusy(false); } }}>Submit for review</button></>}</footer></section></div>;
}

function Fields({ form, set, keys, types, qualities }: any) { return <div className="acc-properties__fields">{keys.map(([key, text, type]: any) => <label key={key}>{text}{type === 'select' ? <select value={form[key] || ''} onChange={e => set(key, e.target.value)}>{(key === 'quality_classification' && qualities ? ['UNRATED', 'ONE', 'TWO', 'THREE', 'FOUR', 'FIVE'] : types || []).map((value: string) => <option value={value} key={value}>{label(value)}</option>)}</select> : type === 'textarea' ? <textarea value={form[key] || ''} onChange={e => set(key, e.target.value)} /> : <input type={type} value={form[key] || ''} onChange={e => set(key, e.target.value)} />}</label>)}</div>; }
function UploadPanel({ label: text, accept, files, onPick, icon }: any) { return <div className="acc-properties__upload"><b>{text}</b><label>{icon}<span>Choose file</span><input type="file" accept={accept} onChange={onPick} /></label>{files.length ? <ul>{files.map((file: any) => <li key={file.id}>{file.original_name || file.caption || 'Uploaded image'}</li>)}</ul> : <p>No file uploaded yet.</p>}</div>; }
function ChoiceGrid({ title: text, values, selected, onToggle, extra }: any) { return <div className="acc-properties__choices"><h3>{text}</h3><div>{values.map((value: string) => <button key={value} className={selected.includes(value) ? 'selected' : ''} onClick={() => onToggle(value)}>{label(value)}</button>)}</div>{extra}</div>; }
function PolicyForm({ form, set }: any) { const policy = form.guest_policy || {}; const toggle = (key: string) => set('guest_policy', { ...policy, [key]: !policy[key] }); return <div className="acc-properties__policy"><h3>Guest policies</h3>{[['children_allowed', 'Children welcome'], ['pets_allowed', 'Pets allowed'], ['smoking_allowed', 'Smoking allowed'], ['events_allowed', 'Private events allowed'], ['visitors_allowed', 'Visitors allowed']].map(([key, text]) => <label key={key}><input type="checkbox" checked={!!policy[key]} onChange={() => toggle(key)} />{text}</label>)}<label>Quiet hours start<input type="time" value={policy.quiet_hours_from || '22:00'} onChange={e => set('guest_policy', { ...policy, quiet_hours_from: e.target.value })} /></label><label>Quiet hours end<input type="time" value={policy.quiet_hours_to || '06:00'} onChange={e => set('guest_policy', { ...policy, quiet_hours_to: e.target.value })} /></label></div>; }
