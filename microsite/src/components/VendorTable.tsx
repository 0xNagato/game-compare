import { useEffect, useMemo, useState } from 'react';

type RetailerRow = {
  retailer: string;
  currency: string;
  fiat: number;
  btc: number;
  recorded_at: string;
  delta_btc: number;
  delta_pct: number;
  is_best: boolean;
};

type RegionEntry = {
  region: string;
  retailers: RetailerRow[];
  summary: {
    min_btc: number;
    max_btc: number;
    spread_btc: number;
    spread_pct: number;
    best_retailer: string | null;
    retailer_count: number;
    sample_count: number;
  };
};

type ApiResponse = {
  product_slug: string;
  include_tax: boolean;
  meta: { unit: string; regions: string[]; updated_at: string };
  regions: RegionEntry[];
};

export default function VendorTable({ slug }: { slug: string }) {
  const [data, setData] = useState<ApiResponse | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState<boolean>(false);
  const [topN, setTopN] = useState<number>(5);

  useEffect(() => {
    if (!slug || slug.trim() === '') return;
    setLoading(true);
    setError(null);
    fetch(`/api/games/${encodeURIComponent(slug)}/vendors?include_tax=true`)
      .then((r) => (r.ok ? r.json() : Promise.reject(new Error(`HTTP ${r.status}`))))
      .then((json) => setData(json))
      .catch((e) => setError(e?.message || 'Failed to load'))
      .finally(() => setLoading(false));
  }, [slug]);

  const rows = useMemo(() => {
    if (!data) return [] as Array<{ id: string; region: string; row: RetailerRow }>;
    const acc: Array<{ id: string; region: string; row: RetailerRow }> = [];
    for (const entry of data.regions || []) {
      const sorted = (entry.retailers || []).slice().sort((a, b) => (a.btc || 0) - (b.btc || 0));
      const limited = topN > 0 ? sorted.slice(0, topN) : sorted;
      for (const r of limited) {
        acc.push({ id: `${entry.region}:${r.retailer}`, region: entry.region, row: r });
      }
    }
    return acc;
  }, [data, topN]);

  if (loading) return <div>Loading vendors…</div>;
  if (error) return <div style={{ color: '#ef4444' }}>Error: {error}</div>;
  if (!data) return <div>Enter a product slug to load vendors.</div>;

  return (
    <div>
      <div style={{ display: 'flex', gap: '0.75rem', alignItems: 'center', marginBottom: '0.5rem' }}>
        <strong>{data.product_slug}</strong>
        <span style={{ color: '#64748b' }}>Updated {new Date(data.meta.updated_at).toLocaleString()}</span>
        <label style={{ marginLeft: 'auto' }}>
          Top N:&nbsp;
          <select value={topN} onChange={(e) => setTopN(Number(e.target.value))}>
            <option value={3}>3</option>
            <option value={5}>5</option>
            <option value={10}>10</option>
            <option value={0}>All</option>
          </select>
        </label>
      </div>
      <div style={{ overflowX: 'auto' }}>
        <table style={{ width: '100%', borderCollapse: 'collapse' }}>
          <thead>
            <tr>
              <th style={th}>Region</th>
              <th style={th}>Retailer</th>
              <th style={th}>Fiat</th>
              <th style={th}>BTC</th>
              <th style={th}>Δ BTC</th>
              <th style={th}>Δ %</th>
              <th style={th}>When</th>
            </tr>
          </thead>
          <tbody>
            {rows.map(({ id, region, row }) => (
              <tr key={id}>
                <td style={td}>{region}</td>
                <td style={td}>{row.retailer}</td>
                <td style={td}>{row.fiat.toFixed(2)} {row.currency}</td>
                <td style={td}>{row.btc.toFixed(6)} BTC</td>
                <td style={td}>{row.is_best ? '—' : `+${row.delta_btc.toFixed(6)} BTC`}</td>
                <td style={td}>{row.is_best ? '—' : `+${row.delta_pct.toFixed(2)}%`}</td>
                <td style={td}>{new Date(row.recorded_at).toLocaleString()}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

const th: React.CSSProperties = {
  textAlign: 'left',
  fontWeight: 600,
  padding: '0.5rem 0.6rem',
  borderBottom: '1px solid #334155'
};
const td: React.CSSProperties = {
  padding: '0.45rem 0.6rem',
  borderBottom: '1px solid #1f2937'
};

