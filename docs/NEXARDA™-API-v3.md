<a href="https://www.nexarda.com/"><img src="https://imgcdn1.nexarda.com/main/static/branding/logo.svg" width="420"></a>

# Available Endpoints
Here is a list of all available endpoints you can use.
* [Website Status](#website-status)
* [Retailers List](#retailers-list)
* [Product Search](#product-search)
* [Product Details](#product-details)
* [Product Prices (Offers)](#product-prices)
* [Random Product Image](#random-product-image)
* [Product Feed](#product-feed)
* [User Avatar](#user-avatar)
* [User Details](#user-details)
* [Product Embed](#product-embed)
* [Find Product (External Providers)](#find-product)

***

## Website Status
**Endpoint URL:** <a href="https://www.nexarda.com/api/v3/status" target="_blank">https://www.nexarda.com/api/v3/status</a> — `GET`<br>
**Endpoint Description:** Fetches the service status of NEXARDA™.

**Example Usage:** I want to check if the website is currently accessible, the `online` value should be `true`.<br>
**Example Response:** Please see the example provided below.

```json
{
    "online": true,
    "under_development": false,
    "reason": "..."
}
```

***

## Retailers List
**Endpoint URL:** <a href="https://www.nexarda.com/api/v3/retailers" target="_blank">https://www.nexarda.com/api/v3/retailers</a> — `GET`<br>
**Endpoint Description:** Fetches the list of currently active approved NEXARDA™ retailers.

**Example Usage:** I want to get the full list of retailers, and loop through the results.<br>
**Example Response:** Please see the example provided below.

```json
[
    {
        "id": 1,
        "name": "Example Store",
        "slug": "example-store",
        "images": {
            "icon": "https://imgcdn1.nexarda.com/main/static/game-stores/example-store.png",
            "logo": "https://imgcdn1.nexarda.com/main/static/game-stores/example-store-large.png"
        },
        "type": "Official Store",
        "is_official_store": true,
        "is_affiliate": false,
        "description": "...",
        "css_class": "store--example-store",
        "website": "https://www.example.com/",
        "previous_names": ["Renamed Example Store", "Another Store Name"],
        "founded": 2005,
        "coupons": [
            {
                "code": "EXAMPLE",
                "terms": "Save 5% on your first purchase!",
                "discount": 5
            },
            ...
        ]
    },
    ...
]
```

***

## Product Search
**Endpoint URL:** <a href="https://www.nexarda.com/api/v3/search" target="_blank">https://www.nexarda.com/api/v3/search</a> — `GET`<br>
**Endpoint Description:** Searches NEXARDA™ for entries including video games, game studios, game franchises, website users and game consoles.

**Example Usage:** I want to search for a video game with the name "Example Game", and fetch the product ID.<br>
**Example Request:** <a href="https://www.nexarda.com/api/v3/search?type=games&q=Example+Game" target="_blank">https://www.nexarda.com/api/v3/search?type=games&q=Example+Game</a><br>
**Example Response:** Please see the example provided below.

```json
{
    "success": true,
    "message": "Showing 50 results for \"Example Game\".",
    "results": {
        "page": 1,
        "pages": 39,
        "shown": 50,
        "total": 1948,
        "items": [
            {
                "type": "Game",
                "title": "Example Game (2008)",
                "text": "This game is currently unavailable",
                "slug": "/games/example-game-(2457)",
                "image": "https://www.example.com/cover.png",
                "game_info": {
                    "id": "2457",
                    "name": "Example Game",
                    "short_desc": "...",
                    "release_date": 1218641841,
                    "upcoming": false,
                    "delisted": false,
                    "cancelled": false,
                    "spotlight": true,
                    "lowest_price": 21.50,
                    "highest_discount": 35,
                    "developers": [708],
                    "publishers": [1543],
                    "platforms": [
                        {
                            "name": "Steam",
                            "slug": "steam",
                            "icon": "fab fa-steam"
                        },
                        ...
                    ],
                    "age_ratings": [
                        {
                            "id": "esrb-mature",
                            "name": "ESRB Mature (17+)"
                        },
                        ...
                    ]
                }
            },
            ...
        ]
    }
}
```

**Query Strings:** You can also use query strings to modify the response or add additional search criteria.<br>
Here is a list of query strings you can use with this endpoint:
* `q` - The search term e.g. "Example Game".
* `type` - Search for a specific type of product (allowed values: games, franchises, users, consoles, summary).
* `currency` - Show prices in a specific currency (allowed values: GBP, EUR, USD).
* `output` - Returns the response in a specific format (allowed values: json, html).
* `page` - Jump to a specific page number (has no effect when `type` is set to "summary").

***

## Product Details
**Endpoint URL:** <a href="https://www.nexarda.com/api/v3/product" target="_blank">https://www.nexarda.com/api/v3/product</a> — `GET`<br>
**Endpoint Description:** Fetches NEXARDA™ product information for a specific item by ID.

**Example Usage:** I want to fetch product information for the video game [Dark Souls Remastered](https://www.nexarda.com/games/dark-souls-remastered-(101)) - the ID of this game is "101".<br>
**Example Request:** <a href="https://www.nexarda.com/api/v3/product?type=game&id=101" target="_blank">https://www.nexarda.com/api/v3/product?type=game&id=101</a><br>
**Example Response:** Please see the example provided below.

<img src="https://lingtalfi.com/services/pngtext?color=ff0000&size=16&text=Note:%20This%20endpoint%20is%20currently%20not%20finished%20and%20may%20change%20in%20the%20future!"><br>

```json
{
    "success": true,
    "message": "Product found (is valid).",
    "product": {
        "type": "game",
        "id": "101",
        "name": "Dark Souls Remastered",
        "slug": "/games/dark-souls-remastered-(101)",
        "images": {
            "cover": "https://www.example.com/cover.png",
            "banner": "https://www.example.com/banner.png"
        },
        "release": 1593388800
    }
}
```

***

## Product Prices
**Endpoint URL:** <a href="https://www.nexarda.com/api/v3/prices" target="_blank">https://www.nexarda.com/api/v3/prices</a> — `GET`<br>
**Endpoint Description:** Fetches all stored product offers for a specific NEXARDA™ product.

**Example Usage:** I want to display all price offers for [Six Days in Fallujah](https://www.nexarda.com/games/six-days-in-fallujah-(2781)) on my website.<br>
**Example Request:** <a href="https://www.nexarda.com/api/v3/prices?type=game&id=2781&currency=GBP" target="_blank">https://www.nexarda.com/api/v3/prices?type=game&id=2781&currency=GBP</a><br>
**Example Response:** Please see the example provided below.

<img src="https://lingtalfi.com/services/pngtext?color=ff0000&size=16&text=Note:%20This%20endpoint%20is%20currently%20not%20finished%20and%20may%20change%20in%20the%20future!"><br>

```json
{
    "success": true,
    "message": "Here are the prices for \"Six Days in Fallujah\".",
    "info": {
        "id": 2781,
        "name": "Six Days in Fallujah",
        "slug": "/games/six-days-in-fallujah-(2781)",
        "cover": "https://www.example.com/cover.png",
        "banner": "https://www.example.com/banner.png",
        "release": 1687392000
    },
    "prices": {
        "currency": "GBP",
        "lowest": 21.25,
        "highest": 32.99,
        "stores": 20,
        "offers": 20,
        "editions": ["Standard Edition"],
        "regions": ["Global", "Europe"],
        "list": [
            {
                "url": "https://www.nexarda.com/redirect/UFVSQ0hBU0VfTElOSyA2MDk3OA==?currency=GBP",
                "store": {
                    "name": "Gamivo",
                    "image": "https://imgcdn1.nexarda.com/main/static/game-stores/gamivo.png",
                    "type": "Marketplace",
                    "official": false
                },
                "edition": "Standard Edition",
                "edition_full": "Standard Edition FOR:WINDOWS",
                "region": "Global",
                "available": false,
                "price": -99,
                "discount": 0,
                "coupon": {
                    "available": false,
                    "discount": 0,
                    "code": "",
                    "price_without": -99,
                    "terms": "No information provided."
                }
            },
            ...
        ]   
    }
}
```

***

## Random Product Image
**Endpoint URL:** <a href="https://www.nexarda.com/api/v3/random-image" target="_blank">https://www.nexarda.com/api/v3/random-image</a> — `GET`<br>
**Endpoint Description:** Displays a random NEXARDA™ product image and redirects to a compressed version of the image.

**Example Usage:** I want to display a random video game hero banner image on my website.<br>
**Example Request:** <a href="https://www.nexarda.com/api/v3/random-image?type=game.banner" target="_blank">https://www.nexarda.com/api/v3/random-image?type=game.banner</a><br>
**Example Response:** Please see the example provided below.

```
Location: https://www.example.com/banner.png
```

**Query Strings:** You can also use query strings to modify the response or add additional search criteria.<br>
Here is a list of query strings you can use with this endpoint:
* `type` - Display a specific type of product image (allowed values: game.banner, game.cover).

***

## Product Feed
**Endpoint URL:** <a href="https://www.nexarda.com/api/v3/feed" target="_blank">https://www.nexarda.com/api/v3/feed</a> — `GET`<br>
**Endpoint Description:** Fetches a list of all products from NEXARDA™. This requires a feed API key - please <a href="https://www.nexarda.com/contact" target="_blank">contact us</a> for one.

**Example Usage:** I want to get the full list of games and consoles/gear, and loop through the results.<br>
**Example Request:** <a href="https://www.nexarda.com/api/v3/feed?key=EXAMPLE_API_KEY" target="_blank">https://www.nexarda.com/api/v3/feed?key=EXAMPLE_API_KEY</a><br>
**Example Response:** Please see the example provided below.

```json
{
    "success": true,
    "message": "Feed built successfully with 2,795 products.",
    "games": [
        {
            "id": 1967,
            "name": "1080° Snowboarding",
            "slug": "/games/1080-snowboarding-(1967)",
            "prices": {
                "GBP": 15,
                "EUR": "unavailable",
                "USD": "unavailable"
            },
            "discounts": {
                "GBP": 0,
                "EUR": 0,
                "USD": 0
            }
        },
        ...
    ],
    "consoles": [
        {
            "id": 19,
            "name": "Nintendo Switch Lite",
            "slug": "/consoles/nintendo-switch-lite-(19)",
            "prices": {
                "GBP": 165,
                "EUR": 270.84,
                "USD": 199.99
            },
            "discounts": {
                "GBP": 0,
                "EUR": 0,
                "USD": 0
            }
        },
        ...
    ]
}
```

**Query Strings:** You can also use query strings to modify the response or add additional search criteria.<br>
Here is a list of query strings you can use with this endpoint:
* `key` - Your given feed API key.

***

## User Avatar
**Endpoint URL:** <a href="https://www.nexarda.com/api/v3/avatar" target="_blank">https://www.nexarda.com/api/v3/avatar</a> — `GET`<br>
**Endpoint Description:** Get the avatar image of a NEXARDA™ user (in PNG, JPG, WEBP or GIF format).

**Example Usage:** I want to get a thumbnail sized avatar of a user (64px by 64px).<br>
**Example Request:** <a href="https://www.nexarda.com/api/v3/avatar?username=NEXARDA&size=64" target="_blank">https://www.nexarda.com/api/v3/avatar?username=NEXARDA&size=64</a><br>

**Query Strings:** You can also use query strings to modify the response or add additional search criteria.<br>
Here is a list of query strings you can use with this endpoint:
* `id` - The ID of the user e.g. "EE3DXqg2iR1GoOJ4uaWJEvbsKHtUjw8Hx6pTPAq6ea7fgR3n".
* `username` - The username of the user e.g. "NEXARDA" (case doesnt matter).
* `size` - The size (in pixels) of the image to return between 16 and 520 (will always be square 1:1 ratio).

***

## User Details
**Endpoint URL:** <a href="https://www.nexarda.com/api/v3/user" target="_blank">https://www.nexarda.com/api/v3/user</a> — `GET`<br>
**Endpoint Description:** Get details of a NEXARDA™ user (e.g. followers, online status and username).

**Example Usage:** I want to fetch the details of a specific user.<br>
**Example Request:** <a href="https://www.nexarda.com/api/v3/user?username=NEXARDA" target="_blank">https://www.nexarda.com/api/v3/user?username=NEXARDA</a><br>
**Example Response:** Please see the example provided below.

```json
{
    "success": true,
    "message": "Here are the details of this user.",
    "code": "user_found",
    "info": {
        "id": "EE3DXqg2iR1GoOJ4uaWJEvbsKHtUjw8Hx6pTPAq6ea7fgR3n",
        "username": "NEXARDA",
        "custom_title": "Game more, for less!",
        "rank": {
            "id": "STAFF_NEXARDA",
            "label": "NEXARDA",
            "name": "NEXARDA (Staff)",
            "hex": "#32a0ac",
            "staff": true
        },
        "profile_page": "https://www.nexarda.com/users/NEXARDA",
        "join_date": 1536697323,
        "followers": 7,
        "reviews_published": 0,
        "forum_posts": 5,
        "blog_posts_published": 0,
        "games_in_wish_list": 0,
        "games_in_library": 0,
        "verified": true,
        "suspended": false,
        "online_status": "Offline"
    }
}
```

**Query Strings:** You can also use query strings to modify the response or add additional search criteria.<br>
Here is a list of query strings you can use with this endpoint:
* `id` - The ID of the user e.g. "EE3DXqg2iR1GoOJ4uaWJEvbsKHtUjw8Hx6pTPAq6ea7fgR3n".
* `username` - The username of the user e.g. "NEXARDA" (case doesnt matter).

## Product Embed
**Endpoint URL:** <a href="https://www.nexarda.com/api/v3/embed" target="_blank">https://www.nexarda.com/api/v3/embed</a> — `GET`<br>
**Endpoint Description:** Displays a product embed block directly on a web page (for website owners).

**Example Usage:** I want to show the compare button embed within a HTML Iframe to place on my website.<br>
**Example Request:** <a href="https://www.nexarda.com/api/v3/embed?type=buy_game_button&id=50" target="_blank">https://www.nexarda.com/api/v3/embed?type=buy_game_button&id=50</a>

**Query Strings:** You can also use query strings to modify the response or add additional search criteria.<br>
Here is a list of query strings you can use with this endpoint:
* `type` - The type of embed (allowed values: buy_game_button, buy_console_button, buy_game_card, buy_console_card).
* `id` - The ID of the product (depending on selected type) e.g. 50.
* `output` - The output/display method (allowed values: html, js) which defaults to "html".

For other button design variants please use the "Embed & Share" option on the website.

## Find Product
**Endpoint URL:** <a href="https://www.nexarda.com/api/v3/find" target="_blank">https://www.nexarda.com/api/v3/find</a> — `GET`<br>
**Endpoint Description:** Find a product and redirect to the page (or output info) from an external product/app ID e.g. Steam.

**Example Usage:** I want to find the NEXARDA™ product page for the game "Warhammer 40,000: Space Marine 2" based off its Steam app ID.<br>
**Example Request:** <a href="https://www.nexarda.com/api/v3/find?steam=2183900" target="_blank">https://www.nexarda.com/api/v3/find?steam=2183900</a>

**Query Strings:** You can also use query strings to modify the response or add additional search criteria.<br>
Here is a list of query strings you can use with this endpoint:
* `output` - The output/display method (allowed values: redirect, json) which defaults to "redirect".
* `steam` - The Steam app ID (e.g. 2183900).
* `gog` - The GOG product ID (e.g. 2147483111).