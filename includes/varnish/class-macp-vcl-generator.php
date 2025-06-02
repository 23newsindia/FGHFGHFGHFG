<?php
class MACP_VCL_Generator {
    public static function generate_vcl() {
        $vcl = <<<'VCL'
vcl 4.0;

import std;

# Default backend definition
backend default {
    .host = "127.0.0.1";
    .port = "8080";
    .first_byte_timeout = 300s;
    .connect_timeout = 5s;
    .between_bytes_timeout = 2s;
}

# ACL for purge requests
acl purge {
    "localhost";
    "127.0.0.1";
}

# Cache static files
sub vcl_recv {
    # Handle PURGE requests
    if (req.method == "PURGE") {
        if (!client.ip ~ purge) {
            return(synth(405, "Not allowed."));
        }
        if (req.http.X-Purge-Method == "regex") {
            ban("obj.http.x-url ~ " + req.url);
            return(synth(200, "Banned"));
        }
        return (purge);
    }

    # Skip cache for excluded paths
    if (req.url ~ "^/my-account/" ||
        req.url ~ "/cart/" ||
        req.url ~ "/checkout/" ||
        req.url ~ "wp-login.php") {
        return (pass);
    }

    # Skip cache for excluded parameters
    if (req.url ~ "[?&](__SID|noCache)=") {
        return (pass);
    }

    # Strip cookies for static files
    if (req.url ~ "\.(gif|jpg|jpeg|png|ico|css|js)$") {
        unset req.http.Cookie;
    }

    # Handle mobile devices
    if (req.http.User-Agent ~ "(?i)mobile|android|iphone|ipad|tablet") {
        set req.http.X-Device = "mobile";
    } else {
        set req.http.X-Device = "desktop";
    }
}

sub vcl_backend_response {
    # Cache static files for 7 days
    if (bereq.url ~ "\.(gif|jpg|jpeg|png|ico|css|js)$") {
        set beresp.ttl = 7d;
        set beresp.grace = 24h;
        unset beresp.http.Set-Cookie;
    }

    # Don't cache if backend sends no-cache headers
    if (beresp.http.Cache-Control ~ "no-cache|no-store|private" ||
        beresp.http.Pragma == "no-cache") {
        set beresp.ttl = 0s;
        set beresp.uncacheable = true;
    }

    # Store the URL for purging
    set beresp.http.x-url = bereq.url;
}

sub vcl_deliver {
    # Remove internal headers before delivery
    unset resp.http.x-url;
    
    # Add debug headers
    if (obj.hits > 0) {
        set resp.http.X-Cache = "HIT";
        set resp.http.X-Cache-Hits = obj.hits;
    } else {
        set resp.http.X-Cache = "MISS";
    }
}

sub vcl_hit {
    if (obj.ttl >= 0s) {
        return (deliver);
    }
    return (fetch);
}

sub vcl_miss {
    return (fetch);
}
VCL;

        return $vcl;
    }
}