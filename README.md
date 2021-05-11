Form obfuscator (anti-spam) for SilverStripe
====
A RequestProcessor filter to automatically obfuscate all form URLs in all HTML output via the ContentController by replacing
them with an encoded (switching between ASCII & hexadecimal) version.

## Example
```
<form action="/cursus/">
```
becomes:
```
<form action="&#47;&#x63;&#117;&#x72;&#115;&#x75;&#115;&#x2f;">
```

## Requirements
* SilverStripe 3+

## Usage
The filter automatically encodes all form action URLS outputted through the
ContentController provided it contains the default text/html header.

No configuration required.