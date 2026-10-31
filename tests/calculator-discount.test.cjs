'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const SCRIPT = fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', 'calculator.js'), 'utf8');

class FakeElement {
  constructor(initialValue = '', length = 1) {
    this.value = initialValue;
    this.textValue = '';
    this.length = length;
    this.events = new Map();
    this.dataset = new Map();
    this.classes = new Set();
    this.children = new Map();
  }

  data(key, value) {
    if (arguments.length === 2) { this.dataset.set(key, value); return this; }
    return this.dataset.get(key);
  }
  find(selector) { return this.children.get(selector) || new FakeElement('', 0); }
  closest() { return this.form || new FakeElement('', 0); }
  val(value) { if (arguments.length === 0) return this.value; this.value = value; return this; }
  text(value) { if (arguments.length === 0) return this.textValue; this.textValue = String(value); return this; }
  on(events, callback) { for (const event of events.split(/\s+/)) this.events.set(event, callback); return this; }
  off(events) { for (const event of events.split(/\s+/)) this.events.delete(event); return this; }
  trigger(event) { const callback = this.events.get(event); if (callback) callback({target: this}); return this; }
  is() { return false; }
  css() { return this; }
  prop() { return this; }
  attr() { return this; }
  show() { return this; }
  hide() { return this; }
  fadeOut() { return this; }
  addClass(name) { this.classes.add(name); return this; }
  removeClass(name) { this.classes.delete(name); return this; }
  toggleClass(name) { if (this.classes.has(name)) this.classes.delete(name); else this.classes.add(name); return this; }
  hasClass(name) { return this.classes.has(name); }
}

function calculateFrontendPrice({threshold, percentage, bags = 6, unitPrice = 10}) {
  const document = {body: {}};
  const absent = new FakeElement('', 0);
  const calculator = new FakeElement();
  const cart = new FakeElement();
  cart.children.set('input.qty', absent);
  calculator.form = cart;
  for (const [key, value] of Object.entries({
    'kg-per-mm': 1,
    'kg-per-m2': 0,
    'kg-per-bag': 1,
    'regular-price': unitPrice,
    'variation-price': unitPrice,
    'discount-threshold': threshold,
    'discount-percentage': percentage,
    'product-type': 'simple',
  })) calculator.data(key, value);

  const mm = new FakeElement(0);
  const area = new FakeElement(0);
  const resultBags = new FakeElement(0);
  const totalPrice = new FakeElement();
  const fields = new Map([
    ['.egaline-mm', mm],
    ['.egaline-m2', area],
    ['.result-bags', resultBags],
    ['.result-kg', new FakeElement()],
    ['.total-price-value', totalPrice],
    ['.calculator-variation-id', new FakeElement()],
    ['.qty-btn', new FakeElement()],
    ['.egaline-mm-label', new FakeElement()],
    ['.egaline-m2-label', new FakeElement()],
    ['.result-bags-label', new FakeElement()],
  ]);
  calculator.children = fields;

  const list = {
    each(callback) { callback.call(calculator, 0, calculator); return this; },
    on() { return this; },
    first() { return calculator; },
    addClass() { return this; },
    length: 1,
  };
  const jquery = (target) => {
    if (target === document) return {ready(callback) { callback(jquery); }};
    if (target === calculator) return calculator;
    if (target === '.egaline-calculator') return list;
    if (target === 'form.cart') return cart;
    if (target === 'form.variations_form') return absent;
    return absent;
  };
  const wp = {i18n: {__: (message) => message}};
  const sandbox = {
    document,
    window: {wp, localStorage: {getItem() { return null; }, setItem() {}}},
    wp,
    jQuery: jquery,
    setTimeout() {},
    console,
  };
  vm.runInNewContext(SCRIPT, sandbox, {filename: 'assets/js/calculator.js', timeout: 5000});
  mm.val(1);
  area.val(bags).trigger('change');
  return totalPrice.text();
}

test('no discount without a positive threshold, even when a percentage is configured', () => {
  assert.equal(calculateFrontendPrice({threshold: 0, percentage: 10}), '60.00 EUR');
});

test('discount is applied when a positive threshold has been reached', () => {
  assert.match(calculateFrontendPrice({threshold: 5, percentage: 10}), /54\.00/);
});

test('discount is not applied below its positive threshold', () => {
  assert.equal(calculateFrontendPrice({threshold: 7, percentage: 10}), '60.00 EUR');
});

test('discount requires a positive percentage', () => {
  assert.equal(calculateFrontendPrice({threshold: 5, percentage: 0}), '60.00 EUR');
});
