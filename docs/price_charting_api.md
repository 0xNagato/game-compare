```
PriceCharting API Documentation
Documentation for all PriceCharting APIs, including the Prices API to get current item prices and the Marketplace API to automate all aspects of finding and selling items on the Marketplace.

Overview
Requests are made over HTTPS
Responses are returned in JSON format
Responses include liberal CORS headers to facilitate cross-site requests
Authentication with a static, shared secret
Example:


    $ curl "https://www.pricecharting.com/api/product?t=c0b53bce27c1bdab90b1605249e600dc43dfd1d5&id=6910"

    {
        "status": "success",
        "id":"6910",
        "product-name":"EarthBound"
        "console-name":"Super Nintendo",
    }
        
Authentication
API's are a premium tool. You must have a paid subscription to access the API.

Each subscription has a unique, 40-character token associated with it. You can find this token by visiting the Subscription page and clicking "API/Download" button. If you have any trouble finding your access token, feel free to contact us.

For example, the demo token used in this documentation is c0b53bce27c1bdab90b1605249e600dc43dfd1d5

Each API call is authenticated by including this token as the t parameter in the HTTP request. This token is specific to your guide and grants access to the data you've purchased. Please keep this token private.

Make a Request
To make an API request, make an HTTP request to the base URL https://www.pricecharting.com plus the path for the API you want. See below for a list of supported APIs. The most common is /api/product. Be sure to include a t parameter with your access token and any other parameters required by the API itself.

Requests should use GET or POST methods. Each API will list the supported method. As a general rule, APIs that display data only will use GET and APIs that create or change data will use POST.

During development, requests can be made by visiting the URL in your web browser or using a tool like curl.

Each API response is a JSON object.
Dates are encoded as YYYY-MM-DD strings.
Prices an integer number of pennies. For example, the amount $17.32 would be encoded as 1732.
Boolean are encoded as boolean.
All other keys are encoded as strings.

Error Handling
Each JSON object contains a "status" key whose value is either success or error. If the status is success, the request was completed successfully. The HTTP status code will be 200.

If the API response status is error, there will also be an error-message key whose value describes what went wrong. The HTTP status code to will fall in the 400 or 500 range, reserved for errors.

 
Download Data via CSV File
In addition to an API, we also offer a CSV download with all the data included. A CSV download is a more efficient way to update lots of data points at one time.

The CSV files are generated once every 24 hours so no need to download it more often than once per day.

CSV column names match the Prices API key description names.

CSV download feature is only available to Legendary subscribers. To access an existing download, visit the Subscriptions page and click "API/Download" button.

 
Prices API
This document describes our API for accessing data you've purchased as part of a subscription. For certain applications, it's more convenient to use this API than to work with a downloaded CSV file.

The API and CSV only support current item values in various grades and conditions.
Historic prices and historic sales are not supported.

Product: /api/product
This API provides data about a single product. Its response is semantically equivalent to a single row in your downloadable CSV price guide. The JSON key names exactly match the column names. Here's a sample response.

Whitespace and comments are added for clarity but are not present in an actual response:


    $ curl "https://www.pricecharting.com/api/product?t=c0b53bce27c1bdab90b1605249e600dc43dfd1d5&id=6910"

    {
        "status": "success",              // response status
        "cib-price": 42995,               // $429.95
        "console-name": "Super Nintendo",
        "id": "6910",                     // unique PriceCharting product ID
        "loose-price": 17244,             // $172.44
        "new-price": 53000,               // $530.00
        "product-name": "EarthBound",
        "release-date": "1995-06-05"      // 5 June 1995
    }
        

Parameters
There are several ways to specify which product you want:

id=6910
PriceCharting product ID number
This number is available in CSV price guides, provided in API responses or available by hovering your mouse over the product title on an item page on PriceCharting.com
upc=045496830434
UPC
The Universal Product Code is a convenient way to identify products for recent systems. Games in the First and Second Generation rarely have UPCs.
q=earthbound
Full text search
Search for a product based on its title and/or console. If multiple products match the search, only the best match is returned. Example queries:

chrono trigger super nintendo
mario n64
super mario
Products: /api/products
This API provides data about multiple products. Product queries can be specified just as with /api/product. The response contains a single products key whose value is a list of the first 20 products matching your search. Here's an example search.


    $ curl "https://www.pricecharting.com/api/products?t=c0b53bce27c1bdab90b1605249e600dc43dfd1d5&q=tactics ogre"

    {
        "status":   "success",
        "products": [
            {
                "console-name": "Playstation",
                "id": "4801",
                "product-name": "Tactics Ogre"
            },
            {
                "console-name": "GameBoy Advance",
                "id": "2611",
                "product-name": "Tactics Ogre"
            },
            {
                "console-name": "PSP",
                "id": "30988",
                "product-name": "Tactics Ogre: Let Us Cling Together"
            }
        ]
    }
        

Parameters
There is one way to specify which products you want:

q=earthbound
Full text search
Search for a product based on its title and/or console. If multiple products match the search, only the best matches are returned. Example queries:

chrono trigger super nintendo
mario n64
charizard #4
Prices API: Description of Keys
Description of JSON keys returned by Prices API and CSV column headers.

Data Name	Description
asin	Unique identifier (ASIN) for this product on Amazon.com
bgs-10-price	Cards: BGS 10
Comics: Graded 10.0 by a grading company
box-only-price	Video Games: Price for the original box only
Cards: Graded 9.5 by a grading company
Comics: Graded 9.2 by a grading company
cib-price	Video Games: Price for item only with original box and manual included
Cards: Graded 7 or 7.5 by a grading company
Comics: Graded 4.0 or 4.5 by a grading company
condition-17-price	Cards: CGC 10
Comics: Graded 9.4 by a grading company
condition-18-price	Cards: SGC 10
console-name	The name of the console on which the item was released.
epid	Unique identifier (ePID) for this product on eBay
gamestop-price	The price that GameStop charges for this game in "Pre-Owned" condition. Make sure you don't charge more than the competition. (Buy/Sell prices above already account for this). The Trade price is what GameStop pays in cash for trade-in games.

These prices are only available for consoles that GameStop sells or trades.
genre	The genre is a single category which describes the item. For example RPG, Fighting, Party, Pokemon Card, etc.
graded-price	Video Games: Price for brand new item graded by WATA or VGA
Cards: Graded 9 by a grading company
Comics: Graded 8.0 or 8.5 by a grading company
id	PriceCharting unique id for a product.
loose-price	Video Games: Price for item only without box or manual
Cards: Ungraded card
Comics: Ungraded comic
manual-only-price	Video Games: Price for the original manual only
Cards: Graded 10 by PSA grading service
Comics: Graded 9.8 by a grading company
new-price	Video Games: Price for item with original packaging and original seal
Cards: Graded 8 or 8.5 by a grading company
Comics: Graded 6.0 or 6.5 by a grading company
product-name	The name of the item.
release-date	The date the item was original released.
retail-cib-buy	The recommended price for retailers buying from a customer in CIB (complete in box) condition.
retail-cib-sell	The recommended price for retailers selling to a customer in CIB (complete in box) condition.
retail-loose-buy	The recommended price for retailers buying from a customer in loose condition.
retail-loose-sell	The recommended price for retailers selling to a customer in loose condition.
retail-new-buy	The recommended price for retailers buying from a customer in brand new condition.
retail-new-sell	The recommended price for retailers selling to a customer in brand new condition.
sales-volume	The yearly units sold
upc	The items in your guide will include a UPC that helps you track the item and sell on some websites. For example, eBay uses UPC to identify products when selling on their site.

UPCs may not be available for older consoles that came out before UPCs were created.
 

 
Marketplace API
This documentation is for our API to automate all of the tasks for selling on the PriceCharting Marketplace.

Offers: /api/offers
This API provides multiple offers that match the parameters provided. Complete details about a single offer can be found using /api/offer-details API.

This is the API sellers will use to check if they have sold any offers. Please throttle requests to the same url to a maximum of once every 5 minutes.

Method: GET

Whitespace and comments are added for clarity but are not present in an actual response:


    $ curl "https://www.pricecharting.com/api/offers?t=YOUR_TOKEN&seller=7cgdyu5ynzos3mxwhfh46xj3f4&status=sold"

    {
        "status":"success",
        "offers":[
            {
                "condition-string":"Normal wear",
                "console-name":"NES",
                "ended-time":"2017-10-31",
                "id":9307,                                       // unique PriceCharting product ID
                "include-string":"Game only",
                "is-available":false,
                "is-ended":true,
                "is-shipped":false,
                "is-sold":true,                                  // has offer sold
                "offer-id":"smzksacsy5i2dsrddxsrlzsp6i",         // unique offer Id
                "offer-status":"sold",                           // offer status
                "offer-url":"/offer/smzksacsy5i2dsrddxsrlzsp6i",
                "price":1473,                                    // $14.73.  current price
                "product-name":"M.C. Kids",
                "sale-time":"2017-10-31",
                "shipped-time":"0001-01-01",                     // zero time since it hasn't shipped
            },
            {
                "cert-id": "5672930123",                         // gradeing company unique identifier for graded item
                "condition-string":"Scratched,Writing",
                "console-name":"NES",
                "ended-time":"2017-10-30",
                "grade-co-id": 1,                                // unique ID for the grading company used
                "id":9729,
                "include-string":"Game, Box, and Manual",
                "is-available":false,
                "is-ended":true,
                "is-shipped":true,
                "is-sold":true,
                "offer-id":"nw6asacmsbvvoq5yeyrkvzcdky",
                "offer-status":"available",
                "offer-url":"/offer/nw6asacmsbvvoq5yeyrkvzcdky",
                "price":6281,
                "product-name":"Shatterhand",
                "sale-time":"2017-10-30",
                "shipped-time":"2017-10-31",
            },
        ],
    }
        

Parameters
There are several ways to specify which offers you want:

status=available
Offer Status (required)
Offers matching a particular status. Valid options are: available, sold, ended, or collection
buyer=7cgdyu5ynzos3mxwhfh46xj3f4
User ID for Buyer
All offers bought by a particular user. Your code is in the url of your purchase history page. Other user's ID can be found on their user profile page.
console=G17
PriceCharting Unique Console ID
Offers matching a particular console. This number is available in the Console ID Table below.
condition-id=2
PriceCharting Condition Id
Offers matching a particular condition/includes ("loose", "cib", "new", etc) This number is available in the Includes ID Table below.
genre=Football
Genre
All offers matching a particular genre ("Systems", "RPG", "Puzzle", etc). The genres table shows all the valid options.
id=6910
PriceCharting Unique Product ID
Offers matching a single product. This number is available in CSV price guides, provided in API responses or available by hovering your mouse over the product title on a product page on PriceCharting.com.
seller=7cgdyu5ynzos3mxwhfh46xj3f4
User ID for Seller
All offers created by particular seller. Your code is in the url of your items for sale page. Other user's ID can be found on their user profile page.
sort=name
Sort By
Choose how the offers are sorted. Can be: name (alphabetical by product), starts (newly listed offers), lowest-price (lowest price offers).
Offer-Publish: /api/offer-publish
The Offer-Publish API publishes one new offer on the Marketplace.

To publish multiple offers at once, you should use offer CSV upload tools.

Method: POST


    $ curl --data "t=YOUR_TOKEN&product=9331&condition-id=1&price-max=901&sku=123abc" https://www.pricecharting.com/api/offer-publish

    {
        "status":"success"
        "cost-basis":125,                                // price you paid for item
        "offer-id":"ccirsewk5mdnoest7xkryagimy",         // the unique ID for this new offer
        "offer-url":"/offer/ccirsewk5mdnoest7xkryagimy", // url for the new offer
        "offer-status":"available",
        "product-name":"Metroid",
        "console-name":"NES",
        "condition-string":"Scratches, Writing",         // condition values end users will see
        "include-string":"Game, Box",                    // include values end users will see
        "has-pictures":false,                            // does the listing have a photo
        "price":901,
        "sku":"123abc",
        "is-collection":false,                           // is the offer in a collection
        "start-time":"2017-11-03",                       // date the offer was listed
        "quantity":1,                                    // quantity owned.  only supported for collections
    }
        

Parameters
These parameters determine the values for the offer you publish.

Either product, upc, asin, epid or offer-id is required.
offer-id will edit an existing offer. product, upc, asin, epid will publish a new offer.

product=9331
PriceCharting Unique Product ID
This number is available in CSV price guides, provided in API responses or available by hovering your mouse over the product title on a product page on PriceCharting.com
Using this parameter will create a new listing.
upc=045496630348
UPC
The UPC for the item you are selling. We will match the UPC to a product in our database.
Using this parameter will create a new listing.
asin=B00004SVYF
Amazon Unique ID
ASIN is Amazon's unique product identifier. We will match the ASIN to a product in our database.
Using this parameter will create a new listing.
epid=6088
eBay Unique ID
EPID is eBay's unique product identifier. We will match the EPID to a product in our database.
Using this parameter will create a new listing.
offer-id=vtdsww72uyslgooawzyyijsllm
PriceCharting Unique Offer ID
The unique ID for an offer. This is provided in the /api/offers API and listed on the website in any offer url.
Using this parameter will edit an existing offer with the parameters included in the request.
price-max=901
Maximum Price (required)
This is the price buyers will pay. This is the starting price when you set a price-min too.

Price in number of pennies. $8.99 is 899.
condition-id=1
Includes (required)
Choose what is included with the listing. Item only, Item, Box, and Manual, Brand New, etc.

Use the includes table for a list of the numeric values for each includes type.

This field determines the value of the listing and corresponds to the values shown on website and API.
add-to-collection=on
Add Item to Collection
Including this parameter will add an offer to your collection instead of listing the product for sale.
cost-basis=125
Price You Paid for Item
The price you paid for the item when you bought it.

Price is number of pennies. $8.99 is 899.
description=A+short+text+description
Offer Description
A text description of the item you are selling. Include information about the item not already provided.

300 character maximum.
broken=on
pristine=on
scratch=on
stickers=on
tear=on
writing=on
Condition Tags
Tags describing the condition of the item. Use all condition tags that apply. For example, a game with scratches and stickers on it would be: scratch=on and stickers=on.

pristine=on is converted into "No blemishes" on the website. This tag excludes all other tags except new.

Ommitting all condition tags defaults the listing to "Normal wear".
price-max=700
Minimum Price
Setting a minimm price turns your listing into a reverse auction. The price will steadily decline over a 30 day period from the price-max until it reaches the price-min or it sells.

Price in number of pennies. $8.99 is 899.
sku=123abc
Your Internal SKU
An internal tracking number you can associate with an offer. A SKU must be unique among your active listings, but can be repeated with ended listings.

64 character maximum. Any alpha-numeric characters.
quantity=1
Quantity Owned
Only supported in collections. The number of copies of this item that are owned.

Any integer number. 1 is default if no quantity is provided
Offer-Details: /api/offer-details
Find full details on an individual offer included buyer's address if the item has sold.

Method: GET


    $ curl "https://www.pricecharting.com/api/offer-details?t=YOUR_TOKEN&offer-id=vtdsww72uyslgooawzyyijsllm"

    {
        "status":"success",
        "buyer": {                                        // buyer's shipping info
            "buyer-email":"example@email.com",
            "shipping-city":"Anytown",
            "shipping-country":"United States",
            "shipping-line1":"123 Smith St",
            "shipping-line2":"",
            "shipping-name":"John Doe",
            "shipping-state":"NY",
            "shipping-zip":"00000-0000"
        },
        "buyer-left-feedback":true,                       // has buyer posted feedback
        "condition":"Normal wear",
        "console-name":"Super Nintendo",
        "cost-basis":125
        "ended-time":"2017-10-31",                        // time the listing ended
        "has-pictures":true,
        "id":7000,
        "includes":"Game only",
        "is-collection":false,
        "is-ended":true,                                  // has offer ended
        "is-price-descending":true,                       // is offer a reverse auction
        "is-refunded":false,                              // has offer been refunded
        "is-shipped":true,                                // has offer been shipped
        "is-sold":true,                                   // has offer sold
        "max-price":12000,
        "min-price":10000,
        "offer-id":"vtdsww72uyslgooawzyyijsllm",          // unique offer ID
        "offer-status":"sold",
        "offer-url":"/offer/vtdsww72uyslgooawzyyijsllm",  // url for offer
        "price":11281,                                    // sales price.  $112.81
        "product-name":"Mega Man 7",
        "refunded-time":"0001-01-01",                     // date of refund.  zero value
        "sale-time":"2017-10-31",
        "seller-left-feedback":true,                      // has seller posted feedback
        "shipped-time":"2017-10-31",                      // date offer shipped.
        "shipping-premium":0,                             // extra paid for shipping internationally
        "sku":"",
        "start-time":"2017-10-20",
        "tracking-number":"9400111234567890000000"        // tracking number for shipment
    }
        

Parameters
offer-id=vtdsww72uyslgooawzyyijsllm
Offer ID (required)
The unique ID for an offer. This is provided in the /api/offers API and listed on the website in any offer url.
Offer-Feedback: /api/offer-feedback
Post feedback rating and comments on a particular offer.

Method: POST


    $ curl --data "t=YOUR_TOKEN&rating=2&comment=Great+buyer&offer-id=vtdsww72uyslgooawzyyijsllm" https://www.pricecharting.com/api/offer-feedback

    {
        "status":"success",
        "offer-id":"vtdsww72uyslgooawzyyijsllm",
        "rating":2,
        "comment":"Great buyer"
    }
        

Parameters
offer-id=vtdsww72uyslgooawzyyijsllm
Offer ID (required)
The unique ID for an offer. This is provided in the /api/offers API and listed on the website in any offer url.
rating=2
Feedback Rating (required)
An integer rating the transaction with the other party.

2 = Great
1 = Good
0 = Ok
-1 = Bad
-2 = Awful
comment=Great+buyer
Feedback Comment
A text comment about the transaction. Let other people know why they received the rating you gave.
Offer-Ship: /api/offer-ship
Mark an offer as shipped and upload a tracking number if available.

Method: POST


    $ curl --data "t=YOUR_TOKEN&tracking-number=901234567890000&offer-id=vtdsww72uyslgooawzyyijsllm" https://www.pricecharting.com/api/offer-ship

    {
        "status":"success",
        "offer-id":"vtdsww72uyslgooawzyyijsllm",
        "is-shipped":true,
        "shipped-time":"2017-10-31",          // the date we were notified of shipping
        "tracking-number": "901234567890000"
    }
        

Parameters
offer-id=vtdsww72uyslgooawzyyijsllm
Offer ID (required)
The unique ID for an offer. This is provided in the /api/offers API and listed on the website in any offer url.
tracking-number=901234567890000
Tracking Number
The tracking number for the offer when it was shipped. Can be a USPS, FedEx, UPS, or any other trackable service. If no tracking number is available, leave this parameter out.

A valid tracking number will be used to give seller's positive feedback on a transaction if the buyer doesn't leave feedback. Tracking must prove the item was shipped within 3 days and delivered to the buyer's address.
Offer-End: /api/offer-end
End an offer so it is no longer available for purchase. If offer is in a collection this removes the item from the collection.

Method: POST


    $ curl --data "t=YOUR_TOKEN&offer-id=vtdsww72uyslgooawzyyijsllm" https://www.pricecharting.com/api/offer-end

    {
        "status":"success",
        "offer-id":"vtdsww72uyslgooawzyyijsllm",
        "is-ended":true,
        "ended-time":"2017-10-31"
    }
        

Parameters
offer-id=vtdsww72uyslgooawzyyijsllm
Offer ID (required)
The unique ID for an offer. This is provided in the /api/offers API and listed on the website in any offer url.
Offer-Refund: /api/offer-refund
Refund the payment for an offer that was already purchased. This immediately refunds the payment from your PayPal account back to the buyer.

Method: POST


    $ curl --data "t=YOUR_TOKEN&offer-id=vtdsww72uyslgooawzyyijsllm" https://www.pricecharting.com/api/offer-refund

    {
        "status":"success",
        "offer-id":"vtdsww72uyslgooawzyyijsllm",
        "is-refunded":true,
        "refunded-time":"2017-10-31"
    }
        

Parameters
offer-id=vtdsww72uyslgooawzyyijsllm
Offer ID (required)
The unique ID for an offer. This is provided in the /api/offers API and listed on the website in any offer url.
Market API: Description of Keys
Description of JSON keys returned by Marketplace APIs.

Data Name	Description
best-competing-price	The best price on the Marketplace for the same product and includes string. This will show the lowest price you are competing with. Only compares 'game only' to other 'game only'. Can be used to adjust your prices if you want to have the lowest prices.
buyer-email	The email address for the buyer. Can be used to send emails to the buyer regarding the transaction. Buyers should not be added to any marketing email lists.
buyer-left-feedback	Has the buyer left feedback for this transaction? True or false
comment	The comment left during a feedback API request
condition-string	A string showing what conditions were selected when describing an offer. This is what other users will see on the website. 'Writing', 'Normal Wear', 'Writing, Scratches' are common examples.
console-name	The name of the console the product is associated with.
ended-time	When an offer was ended. This could be the time the offer was sold or the time the seller ended it. If not ended, this will return a zero time.
has-pictures	Does the offer have at least one photo included with the offer? Listings with photos sell better than listings without photos and they are listed more prominently across the site as well.
id	PriceCharting unique id for a product.
image-url	The main image for a particular offer. The url is relative. Adding a '{{WwwUrl}}' to the front will show the image.
include-string	A string showing what items are included in the offer. 'Game only', 'Game and Box', 'Game, Box, and Manual' are common examples.
is-available	Is the item is still available for sale? True or false.
is-best-priced-offer	Is the offer the lowest priced option for this product on the marketplace? True or false.
is-collection	Is the offer part of a collection instead of a offer for sale? True or false.
is-ended	Has the offer ended. It could have sold or been closed by the seller? True or false.
is-price-descending	Is the offer a reverse auction (price decreases until it reaches the minimum price)? True or false.
is-refunded	Has the offer been refunded? True or false.
is-shipped	Has the offer been shipped? True or false.
is-sold	Has the offer been purchased? True or false.
offer-id	The unique ID for an offer.
offer-status	The current status of the offer. Valid options are 'available', 'sold', 'ended', 'pending', and 'collection'. 'pending' offers have been published, but are not available to purchase due to a vacation hold or a seller payment account issue. 'collection' status is for items listed in a collection instead of listed for sale.
offer-url	The url to see the offer on PriceCharting.com. The url is relative. Adding a '{{WwwUrl}}' to the front completes the url.
pending-reason	The reason the 'offer-status' is 'pending'. This elaborates on why an offer is pending and not available for sale.
price	The current price for an offer or the sales price if the offer has sold. If the offer has sold this also includes any 'shipping-premium' paid. Returns a number of pennies like '999' for $9.99.
price-max	The maximum price the seller entered when publishing an offer. In a standard offer, this is the listing price. In a reverse auction, this is the starting price. Returns a number of pennies like '999' for $9.99.
price-min	The minimum price the seller entered when publishing an offer. This is the minimum price for a reverse auction and blank in a standard listing. Returns a number of pennies like '999' for $9.99.
product-name	The name for a product.
rating	The rating left during a feedback API request. And integer between or equal to -2 and 2.
refunded-time	When an offer was refunded. If not refunded, this will return a zero time.
sale-time	When an offer sold. If not sold, this will return a zero time.
seller-id	The unique user ID for the seller. This can be used to find more details on the seller at their user url, '/user/SELLER-ID'.
seller-left-feedback	Has the seller left feedback for this transaction? True or false
shipped-time	When an offer shipped. This is when the seller notified us of the shipment, not when the seller actually dropped it off with the shipping company. If not shipped, this will return a zero time.
shipping-city	The buyer's city for shipping purposes.
shipping-country	The buyer's country for shipping purposes.
shipping-line1	The buyer's first address line for shipping purposes. Usually a street number and street name.
shipping-line2	The buyer's second address line for shipping purposes. Often times an apartment number. This is blank for addresses with no second line.
shipping-name	The buyer's name for shipping purposes.
shipping-premium	The additional amount the buyer paid for international shipping. This is blank for domestic orders. It is automatically calculated based on the product and weight.
shipping-state	The buyer's state or province for shipping purposes.
shipping-zip	The buyer's postal code for shipping purposes.
sku	The seller's sku for this offer. Will be alphanumeric. This SKU will be unique across active listings. This is only returned if the seller is making the API request.
start-time	When an offer was published. If an offer is revised, this field will reset.
status	The api request status. Either 'succecss' or 'error'.
tracking-number	The tracking number the seller used when shipping the product.
value	Only returned with offers in a collection. This is the current value of the product with the items included (Loose, Complete, or New).
 
Reference Tables
Console ID Table
Console Name	ID
3DO	G25
Action Max	G36545
Amiga	G46
Amiga CD32	G92
Amiibo	G56
Amiibo Cards	G46757
Amstrad CPC	G70472
Amstrad GX4000	G66098
Apple II	G79417
Arcadia 2001	G91
Asian English Nintendo 3DS	G56638
Asian English PSP	G59682
Asian English Playstation 2	G70756
Asian English Playstation 3	G56634
Asian English Playstation 4	G56635
Asian English Playstation 5	G56636
Asian English Playstation Vita	G56637
Asian English Switch	G56633
Asian English Switch 2	G80190
Asian English Xbox	G72899
Asian Xbox 360	G79471
Atari 2600	G24
Atari 2800	G84152
Atari 400	G45
Atari 5200	G31
Atari 7800	G33
Atari Lynx	G26
Atari ST	G146
Atari XE	G72528
Bally Astrocade	G90
CD-i	G37
Casio Loopy	G63134
Club Nintendo Magazine	G77760
Colecovision	G30
Commodore 128	G72569
Commodore 16	G56918
Commodore 64	G28
Disney Infinity	G52
Dreamcast Magazine	G53043
Electronic Gaming Monthly	G53047
Entex Adventure Vision	G72005
Evercade	G28704
FM Towns Marty	G55665
Fairchild Channel F	G83
Famicom	G55
Famicom Disk System	G117
Game & Watch	G68
Game Informer	G53044
Game Wave	G55666
Game.Com	G93
GameBoy	G49
GameBoy Advance	G1
GameBoy Color	G2
GamePro	G53046
Gamecube	G3
Gizmondo	G7728
HyperScan	G55667
Intellivision	G27
JP 3DO	G68532
JP GameBoy	G112
JP GameBoy Advance	G79
JP GameBoy Color	G113
JP Gamecube	G98
JP LaserActive	G68060
JP MSX	G122
JP MSX2	G128
JP Neo Geo AES	G115
JP Neo Geo CD	G43342
JP Neo Geo MVS	G114
JP Neo Geo Pocket	G59835
JP Neo Geo Pocket Color	G129
JP Nintendo 3DS	G119
JP Nintendo 64	G99
JP Nintendo DS	G111
JP Nintendo Switch	G100
JP Nintendo Switch 2	G80189
JP PC Engine	G82
JP PC Engine CD	G154
JP PSP	G110
JP Playstation	G107
JP Playstation 2	G108
JP Playstation 3	G109
JP Playstation 4	G118
JP Playstation 5	G7470
JP Playstation Vita	G106
JP Sega Dreamcast	G64
JP Sega Game Gear	G74
JP Sega Mark III	G6900
JP Sega Mega CD	G140
JP Sega Mega Drive	G105
JP Sega Pico	G137
JP Sega Saturn	G67
JP Super 32X	G142
JP Virtual Boy	G76
JP Wii	G102
JP Wii U	G104
JP Xbox	G125
JP Xbox 360	G124
JP Xbox One	G126
JP Xbox Series X	G7587
Jaguar	G21
Jaguar CD	G65467
LaserActive	G66470
Lego Dimensions	G144
Macintosh	G81500
Magnavox Odyssey	G130
Magnavox Odyssey 2	G36
Magnavox Odyssey 300	G131
Mattel Aquarius	G132
MegaZone	G58494
Microvision	G6956
Mini Arcade	G69
N-Gage	G42
NES	G17
Neo Geo AES	G116
Neo Geo CD	G60
Neo Geo MVS	G18
Neo Geo Pocket Color	G40
Nintendo 3DS	G39
Nintendo 64	G4
Nintendo DS	G5
Nintendo Power	G81
Nintendo Switch	G59
Nintendo Switch 2	G80178
Nuon	G72978
Official Nintendo Magazine	G77563
Official US Playstation Magazine	G73272
PAL 3DO	G68531
PAL Amiga CD32	G153
PAL Amstrad GX4000	G72181
PAL Atari 2600	G72094
PAL Atari 7800	G62701
PAL Dreamcast Magazine	G85742
PAL Evercade	G61287
PAL GameBoy	G73
PAL GameBoy Advance	G80
PAL GameBoy Color	G77
PAL Gamecube	G70
PAL MSX	G123
PAL MSX2	G127
PAL Mega Drive 32X	G141
PAL N-Gage	G63150
PAL NES	G58
PAL Neo Geo Pocket	G61530
PAL Neo Geo Pocket Color	G37074
PAL Nintendo 3DS	G94
PAL Nintendo 64	G62
PAL Nintendo DS	G78
PAL Nintendo Switch	G87
PAL Nintendo Switch 2	G80188
PAL PSP	G84
PAL Playstation	G72
PAL Playstation 2	G63
PAL Playstation 3	G75
PAL Playstation 4	G86
PAL Playstation 5	G7469
PAL Playstation Vita	G101
PAL Sega Dreamcast	G65
PAL Sega Game Gear	G121
PAL Sega Master System	G51
PAL Sega Mega CD	G139
PAL Sega Mega Drive	G71
PAL Sega Pico	G138
PAL Sega Saturn	G97
PAL Super Nintendo	G61
PAL Vectrex	G133
PAL Videopac G7000	G134
PAL Videopac G7400	G135
PAL Wii	G85
PAL Wii U	G96
PAL Xbox	G88
PAL Xbox 360	G89
PAL Xbox One	G95
PAL Xbox Series X	G7586
PC FX	G6955
PC Gamer Magazine	G53045
PC Games	G145
PSP	G9
Pippin	G55669
Playstation	G6
Playstation 2	G7
Playstation 3	G12
Playstation 4	G53
Playstation 5	G7468
Playstation Vita	G43
Pokemon Mini	G143
Polymega	G76600
RCA Studio II	G87937
Rumble U	G43345
Sega 32X	G50
Sega CD	G23
Sega Dreamcast	G16
Sega Game Gear	G20
Sega Genesis	G15
Sega Master System	G29
Sega Pico	G136
Sega Saturn	G14
Sega Saturn Magazine	G85478
Sharp X68000	G61110
Sinclair ZX81	G77384
Skylanders	G48
Starlink	G43344
Stoneheart	G60326
Strategy Guide	G26297
Super Famicom	G66
Super Nintendo	G13
Supervision	G55668
TI-99	G120
TRS-80	G75713
Tapwave Zodiac	G51260
Tiger R-Zone	G43401
TurboGrafx CD	G6927
TurboGrafx-16	G19
UB Funkeys	G75110
VTech Socrates	G56889
Vectrex	G32
Vic-20	G44
Virtual Boy	G22
Wholesale	G57
Wii	G11
Wii U	G47
WonderSwan	G151
WonderSwan Color	G152
Xbox	G8
Xbox 360	G10
Xbox One	G54
Xbox Series X	G7585
ZX Spectrum	G44257
Genre Table
Genre Name
Accessories
Action & Adventure
Arcade
Baseball
Beat'em Up
Basketball
Board & Card
Casino
Compilation
Controllers
Dance
Demo & NFR
Extreme Sports
Educational
Fighting
Football
FPS
Golf
Hack and Slash
Horror
Light Gun
Magazine
Mini Games
Movie
Music
Other
Party
Pinball
Platformer
Puzzle
Racing
RPG
Simulation
Shoot'em Up
Soccer
Sports
Strategy
Systems
Third Person Shooter
Trivia
Visual Novel
Wrestling
Condition Table
Condition ID	Video Games	Cards	Comics	Coins
1	Loose	Ungraded	Ungraded	Ungraded
2	New & Sealed	Grade 8	6.0	AU (50-58)
3	CIB	Grade 7	4.0	VF (20-35)
5	Graded	Grade 9	8.0	MS62
6	Box only	Grade 9.5	9.2	MS64
7	Manual only	Grade 10	9.8	MS66
8	Item and Box	BGS 10	10.0	F (12-15)
9	Item and Manual	Grade 1	2.0	XF (40-45)
10	Box and Manual	Grade 2	9.6	MS60
13	Graded CIB	Grade 3	3.0	MS61

```