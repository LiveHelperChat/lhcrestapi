# Install

Sample extension how to extend Rest API calls. Add custom variables or override request.

```
cd lhc_web/extension && git clone https://github.com/LiveHelperChat/lhcrestapi.git
// Modify main settings file and activate extension
'extensions' =>
      array (
         'lhcrestapi'
),
// Setup your Rest API call and play around with
`extension/lhcrestapi/bootstrap/bootstrap.php`
``