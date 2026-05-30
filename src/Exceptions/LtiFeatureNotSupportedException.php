<?php

namespace RefBytes\Lti\Exceptions;

/**
 * Thrown when calling a feature that is not supported by the launch's LTI
 * version (e.g. AGS line items on an LTI 1.1 launch, or Basic Outcomes on an
 * LTI 1.3 launch without the AGS claim).
 */
class LtiFeatureNotSupportedException extends LtiException {}
