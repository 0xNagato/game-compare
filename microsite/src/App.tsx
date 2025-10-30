import { useMemo, useState } from 'react';
import VendorTable from './components/VendorTable';

const App = () => {
  const today = useMemo(() => new Date().toLocaleString(), []);

  const [slug, setSlug] = useState<string>('sluggy-product');

  return (
    <main className="app-shell">
      <section>
        <h1>Game Price Compare Microsite</h1>
        <p>
          This placeholder React application was scaffolded with Vite. Replace this content with
          the Leaflet-based choropleth map and chart experiences once the API endpoints are ready.
        </p>
        <p className="timestamp">Scaffold generated: {today}</p>
      </section>
      <section>
        <h2>Compact Vendor Table</h2>
        <div style={{ display: 'flex', gap: '0.5rem', alignItems: 'center' }}>
          <label htmlFor="slugInput">Product slug</label>
          <input
            id="slugInput"
            value={slug}
            onChange={(e) => setSlug(e.target.value)}
            placeholder="e.g. starfield"
            style={{ padding: '0.4rem 0.6rem' }}
          />
          <small>(loads from /api/games/{'{slug}'}/vendors)</small>
        </div>
        <div style={{ marginTop: '1rem' }}>
          <VendorTable slug={slug} />
        </div>
      </section>
    </main>
  );
};

export default App;
