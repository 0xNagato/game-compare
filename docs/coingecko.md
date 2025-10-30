CoinGecko API Documentation
Overview
The CoinGecko API provides comprehensive cryptocurrency market data, including prices, market capitalization, volume, and more. Developers can utilize this API to access real-time and historical data on thousands of cryptocurrencies.

Base URL: http://api.coingecko.com/api/v3

API Endpoints
Get cryptocurrency market data
GET /coins/markets

Description:
Retrieve information about the specified cryptocurrency markets.

Parameters:
- vs_currency: The target currency to convert the cryptocurrency values into (e.g., usd, eur).
- ids: A comma-separated list of cryptocurrency IDs to retrieve data for.
- order: Sort the results based on market cap (market_cap_desc) or rank (gecko_desc).
- per_page: Number of results to return per page.
- page: Page number.

Example:
GET http://api.coingecko.com/api/v3/coins/markets?vs_currency=usd&ids=bitcoin,ethereum&order=market_cap_desc&per_page=10&page=1
Get cryptocurrency historical data
GET /coins/{id}/market_chart

Description:
Retrieve historical market data for a specific cryptocurrency.

Parameters:
- id: Cryptocurrency ID (e.g., bitcoin, ethereum).
- vs_currency: The target currency to fetch historical data in (e.g., usd).
- days: Number of days to retrieve historical data for.

Example:
GET http://api.coingecko.com/api/v3/coins/bitcoin/market_chart?vs_currency=usd&days=30
List supported cryptocurrencies
GET /coins/list

Description:
Fetch a list of supported cryptocurrencies and their basic information.

Example:
GET http://api.coingecko.com/api/v3/coins/list
Get cryptocurrency information
GET /coins/{id}

Description:
Retrieve detailed information about a specific cryptocurrency.

Parameters:
- id: Cryptocurrency ID (e.g., bitcoin, ethereum).

Example:
GET http://api.coingecko.com/api/v3/coins/bitcoin