"use client";

/**
 * idexal Os renders numbers with Latin (ASCII) digits in every language: it
 * shows 9,073,504, never ٩٬٠٧٣٬٥٠٤. A browser whose locale uses the `arab`
 * numbering system would otherwise turn every default `Intl` and `toLocale*`
 * call into Eastern Arabic-Indic digits. This module pins the `nu-latn`
 * numbering system onto every locale those formatters receive, so only digit
 * shapes and group separators are pinned: date order, month names, currency
 * symbols and time zones stay correct for the requested locale.
 *
 * Importing this module installs the overrides once for the running process.
 * Rendering <LatinNumerals /> from the root layout is what loads it.
 */

const numberFormat = Intl.NumberFormat;
const dateTimeFormat = Intl.DateTimeFormat;

/** The runtime's default locale, read before any override is installed. */
const runtimeLocale = numberFormat().resolvedOptions().locale;

/** Pins Latin digits on one locale tag, dropping any numbering system it carries. */
function toLatinLocale(locale: string | Intl.Locale): string {
  const tag = typeof locale === "string" ? locale : locale.toString();
  const [base] = tag.split("-u-");
  return `${base}-u-nu-latn`;
}

/** Pins Latin digits on a locales argument; a missing locale falls back to the runtime default. */
function toLatinDigits(locales?: Intl.LocalesArgument): Intl.LocalesArgument {
  if (typeof locales === "string") {
    return toLatinLocale(locales);
  }
  if (locales instanceof Intl.Locale) {
    return toLatinLocale(locales);
  }
  if (Array.isArray(locales) && locales.length > 0) {
    return locales.map(toLatinLocale);
  }
  return toLatinLocale(runtimeLocale);
}

function install(): void {
  Intl.NumberFormat = new Proxy(numberFormat, {
    construct: (target, args) => new target(toLatinDigits(args[0]), args[1]),
    apply: (target, _thisArgument, args) => target(toLatinDigits(args[0]), args[1]),
  });

  Intl.DateTimeFormat = new Proxy(dateTimeFormat, {
    construct: (target, args) => new target(toLatinDigits(args[0]), args[1]),
    apply: (target, _thisArgument, args) => target(toLatinDigits(args[0]), args[1]),
  });

  const numberToLocaleString = Number.prototype.toLocaleString;
  Number.prototype.toLocaleString = function (this: number, locales?: Intl.LocalesArgument, options?: Intl.NumberFormatOptions): string {
    return numberToLocaleString.call(this, toLatinDigits(locales), options);
  };

  const bigIntToLocaleString = BigInt.prototype.toLocaleString;
  BigInt.prototype.toLocaleString = function (this: bigint, locales?: Intl.LocalesArgument, options?: BigIntToLocaleStringOptions): string {
    return bigIntToLocaleString.call(this, toLatinDigits(locales), options);
  };

  const dateToLocaleString = Date.prototype.toLocaleString;
  Date.prototype.toLocaleString = function (this: Date, locales?: Intl.LocalesArgument, options?: Intl.DateTimeFormatOptions): string {
    return dateToLocaleString.call(this, toLatinDigits(locales), options);
  };

  const dateToLocaleDateString = Date.prototype.toLocaleDateString;
  Date.prototype.toLocaleDateString = function (this: Date, locales?: Intl.LocalesArgument, options?: Intl.DateTimeFormatOptions): string {
    return dateToLocaleDateString.call(this, toLatinDigits(locales), options);
  };

  const dateToLocaleTimeString = Date.prototype.toLocaleTimeString;
  Date.prototype.toLocaleTimeString = function (this: Date, locales?: Intl.LocalesArgument, options?: Intl.DateTimeFormatOptions): string {
    return dateToLocaleTimeString.call(this, toLatinDigits(locales), options);
  };
}

install();

export function LatinNumerals(): null {
  return null;
}
