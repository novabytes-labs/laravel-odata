<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Response Format
    |--------------------------------------------------------------------------
    |
    | Controls how query results are formatted.
    |
    | 'laravel' - Standard Laravel pagination/collection format.
    | 'odata'   - OData JSON format with @odata.count, value, @odata.nextLink.
    |
    */
    'response_format' => 'laravel',

    /*
    |--------------------------------------------------------------------------
    | Maximum Expand Depth
    |--------------------------------------------------------------------------
    |
    | Limits how deeply nested $expand can go to prevent abuse.
    | For example, $expand=Category($expand=Parent($expand=Root)) has depth 3.
    |
    */
    'max_expand_depth' => 3,

    /*
    |--------------------------------------------------------------------------
    | Maximum $top Value
    |--------------------------------------------------------------------------
    |
    | The maximum number of items a client can request via $top.
    | Set to null to allow any value.
    |
    */
    'max_top' => 1000,

    /*
    |--------------------------------------------------------------------------
    | Default $top Value
    |--------------------------------------------------------------------------
    |
    | Applied when the client does not specify $top.
    | Set to null to return all results by default.
    |
    */
    'default_top' => null,

    /*
    |--------------------------------------------------------------------------
    | Throw on Invalid Query Options
    |--------------------------------------------------------------------------
    |
    | When true, requesting a filter, sort, expand, or select that is not in
    | the allowlist throws an InvalidQueryException (HTTP 400).
    |
    | When false, invalid options are silently ignored.
    |
    */
    'throw_on_invalid' => true,

];
