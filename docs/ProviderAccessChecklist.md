# Provider Access Checklist

Summary of credentials and public rate/usage guidance for every configured pricing provider.

| Provider | Coverage Snapshot | Auth Required | Free Tier Details | Suggested Throttle |
| --- | --- | --- | --- | --- |
| Nexarda | US/GB/EU via aggregator | API key (free with account) | Free collector key; manual approval but no cost | ≤20 req/min per product |
| GiantBomb | Global metadata focus on console/PC | API key (free with registration) | Create GiantBomb API key; include portfolio UA string | ≤60 req/hr; reuse search cache |
| Steam Store (stub) | 20 markets listed in config | None | Unofficial JSON; respect community norms | ≤30 req/min overall, cache 15m |
| IsThereAnyDeal | US, CA, EU, UK, AU/NZ, JP, KR, BR, MX, ZA, IN | API key (free) | Sign up at https://isthereanydeal.com/apps/ to generate key | ≤60 req/min per docs |
| Nintendo eShop | Americas, Europe, APAC key markets (active) | None | Public endpoint; rate caps undocumented | ≤20 req/min, cache 60m |
| PlayStation Store (stub) | Americas, EMEA, APAC locales | None | Requires scraping catalog JSON; honor robots/ToS | ≤20 req/min per locale |
| Microsoft/Xbox Store (stub) | Global DisplayCatalog markets | OAuth client (free Azure app) | Create Azure AD app—no paid tier needed | ≤20 req/sec burst, prefer ≤200 req/min |
| PriceCharting Secondary Market | US market loose/complete/new (active) | API token (free) | Request token via https://www.pricecharting.com/api | ≤5 req/sec, avoid >10k/day |
| TheGamesDB Mirror | Global metadata (self-hosted mirror; curated 80+ flagship games + hardware) | None (mirror synced from upstream keys) | Mirror refreshed nightly via private key; local read-only catalogue | Local mirror: cache in Redis/filesystem |
| eBay Browse (stub) | US, CA, GB, DE, FR, IT, ES, AU, NZ, JP, SG, HK | OAuth app ID (free) | Create eBay developer account; Browse API in default quota | ≤5 req/sec, ≤5k calls/day |
| CoinGecko FX | BTC vs major fiats | None, optional key | Free tier generous; optional API key for priority | ≤50 req/min |

**Next Steps**
1. Register or capture free credentials where required (Nexarda, ITAD, Microsoft, PriceCharting, eBay, TheGamesDB). Store them in `.env` only.
2. Once credentials are in place, enable individual providers (`config/pricing.php`) and wire concrete client implementations.
3. Monitor rate headers and tune queue concurrency per provider guidelines above.
