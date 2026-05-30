<?php

namespace RefBytes\Lti\Exceptions;

/**
 * Thrown for OAuth 1.0a-related failures during LTI 1.1 launch validation:
 * signature mismatch, replayed nonce, stale timestamp, unsupported signature
 * method.
 */
class LtiOAuth1Exception extends LtiException {}
