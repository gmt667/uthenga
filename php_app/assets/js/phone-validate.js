/**
 * Uthenga — Malawian Mobile Number Validation
 * Airtel Money: 099 / 098 · TNM Mpamba: 088 / 089
 * Accepts a leading +265, 265, or 0; spaces/dashes/parens are ignored.
 */
(function (window) {
  'use strict';

  function normalizeMalawiPhone(raw) {
    var digits = String(raw || '').replace(/[\s\-().]/g, '');
    if (digits.charAt(0) === '+') digits = digits.slice(1);
    if (digits.indexOf('265') === 0 && digits.length > 9) {
      digits = '0' + digits.slice(3);
    }
    if (digits.charAt(0) !== '0' && digits.length === 9) {
      digits = '0' + digits;
    }
    if (!/^0\d{9}$/.test(digits)) return null;
    return digits;
  }

  function detectMalawiNetwork(normalized) {
    if (!normalized) return null;
    var prefix = normalized.slice(0, 3);
    if (prefix === '099' || prefix === '098') return 'airtel';
    if (prefix === '088' || prefix === '089') return 'tnm';
    return null;
  }

  function validateMalawiPhone(raw) {
    var normalized = normalizeMalawiPhone(raw);
    if (!normalized) {
      return { valid: false, network: null, normalized: null, message: 'Enter a 10-digit Malawian mobile number (e.g. 099X XXX XXX).' };
    }
    var network = detectMalawiNetwork(normalized);
    if (!network) {
      return { valid: false, network: null, normalized: normalized, message: 'Use an Airtel (099/098) or TNM Mpamba (088/089) number.' };
    }
    var label = network === 'airtel' ? 'Airtel Money' : 'TNM Mpamba';
    return { valid: true, network: network, normalized: normalized, message: 'Recognized as ' + label + '.' };
  }

  window.UthengaPhone = {
    normalize: normalizeMalawiPhone,
    detectNetwork: detectMalawiNetwork,
    validate: validateMalawiPhone
  };
})(window);
