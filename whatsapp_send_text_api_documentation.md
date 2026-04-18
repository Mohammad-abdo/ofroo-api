# WhatsApp Send Text API Documentation

## Endpoint

Example:

    POST https://evo.welniz.org/message/sendText/Ofroo

------------------------------------------------------------------------

## Headers

  Key            Type     Required   Description
  -------------- -------- ---------- ----------------------------
  Content-Type   string   Yes        Must be `application/json`
  apikey         string   Yes        Your Welniz API key

Example:

    Content-Type: application/json
    apikey: YOUR_API_KEY

------------------------------------------------------------------------

## Request Body (JSON)

  ------------------------------------------------------------------------
  Field                Type           Required        Description
  -------------------- -------------- --------------- --------------------
  number               string         Yes             Recipient phone
                                                      number (digits only,
                                                      no + or spaces)

  text                 string         Yes             Message content

  linkPreview          boolean        No              Enable/disable link
                                                      preview (default:
                                                      false)
  ------------------------------------------------------------------------

Example:

``` json
{
  "number": "201234567890",
  "text": "test message",
  "linkPreview": true
}
```

------------------------------------------------------------------------

## cURL Example

``` bash
curl -X POST "https://evo.welniz.org/message/sendText/Ofroo" \
  -H "Content-Type: application/json" \
  -H "apikey: YOUR_API_KEY" \
  -d '{
        "number": "201234567890",
        "text": "test message",
        "linkPreview": true
      }'
```

------------------------------------------------------------------------

## Success Response

**Status Code:** `200 OK` or `201 Created`

Example:

``` json
{
  "status": "success",
  "messageId": "ABC123XYZ"
}
```

------------------------------------------------------------------------

## Error Response

**Status Code:** `400`, `401`, `404`, `500`

Example:

``` json
{
  "status": "error",
  "message": "Invalid API key"
}
```

------------------------------------------------------------------------

## Notes

-   Phone numbers must contain digits only (no spaces, +, or special
    characters).
-   API key must be valid and active.
-   Instance name must exist and be connected.
-   Recommended timeout: 10 seconds.
