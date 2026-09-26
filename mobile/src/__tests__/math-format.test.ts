import { ago, clock, dateTime, daysUntil, formatBytes, greeting, initials, plural } from '@/core/format';
import { hasMath, mathToText } from '@/core/math';

describe('mathToText', () => {
  const cases: [string, string][] = [
    ['\\( x^2 + 1 \\)', 'x² + 1'],
    ['\\(x^{10}\\)', 'x¹⁰'],
    ['\\( a_1 + a_2 \\)', 'a₁ + a₂'],
    ['\\( \\frac{1}{2} \\)', '1/2'],
    ['\\( \\frac{a+b}{c} \\)', '(a+b)/c'],
    ['\\( \\sqrt{16} = 4 \\)', '√16 = 4'],
    ['\\( \\sqrt{x+1} \\)', '√(x+1)'],
    ['\\( 5 \\times 3 \\div 1 \\)', '5 × 3 ÷ 1'],
    ['\\( \\pi r^2 \\)', 'πr²'],
    ['\\( 30^\\circ \\)', '30°'],
    ['\\( x \\le 5 \\ne 4 \\)', 'x ≤ 5 ≠ 4'],
    ['\\( \\text{Speed} = \\frac{d}{t} \\)', 'Speed = d/t'],
    ['\\( \\Delta T = 20 \\)', 'ΔT = 20'],
    ['\\( 2\\pi \\sqrt{\\frac{l}{g}} \\)', '2π √(l/g)'],
    ['\\( \\frac{\\frac{1}{2}}{3} \\)', '(1/2)/3'],
    ['\\( H_2O \\)', 'H₂O'],
    ['\\( x^{a+b} \\)', 'xᵃ⁺ᵇ'],
    ['$$ E = mc^2 $$', 'E = mc²'],
    ['\\[ \\alpha + \\beta \\]', 'α + β'],
  ];

  it.each(cases)('%s', (input, expected) => {
    expect(mathToText(input)).toBe(expected);
  });

  it('leaves the text around maths alone', () => {
    expect(mathToText('Solve \\( x^2 = 9 \\) for x. Cost: $5 or ₦500.')).toBe('Solve x² = 9 for x. Cost: $5 or ₦500.');
  });

  it('leaves plain text untouched', () => {
    expect(mathToText('No maths here, just 2^3 and a_b.')).toBe('No maths here, just 2^3 and a_b.');
  });

  it('keeps an exponent it cannot write with Unicode readable', () => {
    expect(mathToText('\\( x^{q+1} \\)')).toBe('x^(q+1)');
  });

  it('does not crash on unmatched braces or unknown commands', () => {
    expect(() => mathToText('\\( \\frac{1 \\)')).not.toThrow();
    expect(mathToText('\\( \\weird{x} \\)')).toContain('weird');
  });

  it('spots maths', () => {
    expect(hasMath('a \\( b \\) c')).toBe(true);
    expect(hasMath('plain')).toBe(false);
  });
});

describe('format', () => {
  it('formatBytes', () => {
    expect(formatBytes(500)).toBe('500 B');
    expect(formatBytes(2048)).toBe('2 KB');
    expect(formatBytes(1)).toBe('1 B');
    expect(formatBytes(1572864)).toBe('1.5 MB');
    expect(formatBytes(1500)).toBe('1 KB');
  });

  it('daysUntil counts whole days and hides past dates', () => {
    const now = new Date(2026, 8, 26, 15, 0);
    expect(daysUntil('2026-09-26', now)).toBe(0);
    expect(daysUntil('2026-10-01', now)).toBe(5);
    expect(daysUntil('2027-02-15', now)).toBe(142);
    expect(daysUntil('2026-09-25', now)).toBeNull();
    expect(daysUntil(null, now)).toBeNull();
    expect(daysUntil('not a date', now)).toBeNull();
  });

  it('greeting follows the hour', () => {
    expect(greeting(new Date(2026, 0, 1, 8))).toBe('Good morning');
    expect(greeting(new Date(2026, 0, 1, 13))).toBe('Good afternoon');
    expect(greeting(new Date(2026, 0, 1, 21))).toBe('Good evening');
  });

  it('clock shows minutes and hours', () => {
    expect(clock(125)).toBe('02:05');
    expect(clock(3725)).toBe('1:02:05');
    expect(clock(0)).toBe('00:00');
    expect(clock(-5)).toBe('00:00');
  });

  it('dateTime uses the app-wide format', () => {
    expect(dateTime(new Date(2026, 2, 12, 21, 24).toISOString())).toBe('12 March 2026 9:24 pm');
    expect(dateTime(new Date(2026, 0, 5, 0, 5).toISOString())).toBe('5 January 2026 12:05 am');
    expect(dateTime('garbage')).toBe('');
  });

  it('ago', () => {
    const now = new Date('2026-09-26T12:00:00Z');
    expect(ago('2026-09-26T11:59:30Z', now)).toBe('just now');
    expect(ago('2026-09-26T11:45:00Z', now)).toBe('15 minutes ago');
    expect(ago('2026-09-26T09:00:00Z', now)).toBe('3 hours ago');
    expect(ago('2026-09-25T09:00:00Z', now)).toBe('yesterday');
    expect(ago('2026-09-20T09:00:00Z', now)).toBe('6 days ago');
  });

  it('initials and plural', () => {
    expect(initials('Chidinma Okafor')).toBe('CO');
    expect(initials('  ada ')).toBe('A');
    expect(plural(1, 'day')).toBe('day');
    expect(plural(2, 'day')).toBe('days');
  });
});
