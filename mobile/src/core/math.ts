/**
 * Turns the LaTeX maths that examiners type in questions (\( x^2 + 1 \), \frac{a}{b}, \sqrt{x} ...) into
 * plain text with Unicode symbols, so it reads well on a phone without a heavy maths renderer.
 * It covers what school exams need. Anything it cannot convert cleanly is left readable, not broken.
 */

const SUPER: Record<string, string> = {
  '0': '⁰', '1': '¹', '2': '²', '3': '³', '4': '⁴', '5': '⁵', '6': '⁶', '7': '⁷', '8': '⁸', '9': '⁹',
  '+': '⁺', '-': '⁻', '−': '⁻', '=': '⁼', '(': '⁽', ')': '⁾',
  a: 'ᵃ', b: 'ᵇ', c: 'ᶜ', d: 'ᵈ', e: 'ᵉ', f: 'ᶠ', g: 'ᵍ', h: 'ʰ', i: 'ⁱ', j: 'ʲ', k: 'ᵏ', l: 'ˡ', m: 'ᵐ',
  n: 'ⁿ', o: 'ᵒ', p: 'ᵖ', r: 'ʳ', s: 'ˢ', t: 'ᵗ', u: 'ᵘ', v: 'ᵛ', w: 'ʷ', x: 'ˣ', y: 'ʸ', z: 'ᶻ',
};

const SUB: Record<string, string> = {
  '0': '₀', '1': '₁', '2': '₂', '3': '₃', '4': '₄', '5': '₅', '6': '₆', '7': '₇', '8': '₈', '9': '₉',
  '+': '₊', '-': '₋', '−': '₋', '=': '₌', '(': '₍', ')': '₎',
  a: 'ₐ', e: 'ₑ', h: 'ₕ', i: 'ᵢ', j: 'ⱼ', k: 'ₖ', l: 'ₗ', m: 'ₘ', n: 'ₙ', o: 'ₒ', p: 'ₚ', r: 'ᵣ', s: 'ₛ', t: 'ₜ', u: 'ᵤ', v: 'ᵥ', x: 'ₓ',
};

const SYMBOLS: Record<string, string> = {
  times: '×', div: '÷', pm: '±', mp: '∓', cdot: '·', le: '≤', leq: '≤', ge: '≥', geq: '≥', ne: '≠', neq: '≠',
  approx: '≈', equiv: '≡', sim: '∼', propto: '∝', infty: '∞', degree: '°', circ: '°', angle: '∠', triangle: '△',
  perp: '⊥', parallel: '∥', therefore: '∴', because: '∵', rightarrow: '→', to: '→', leftarrow: '←', Rightarrow: '⇒',
  Leftrightarrow: '⇔', leftrightarrow: '↔', rightleftharpoons: '⇌', uparrow: '↑', downarrow: '↓',
  in: '∈', notin: '∉', subset: '⊂', cup: '∪', cap: '∩', emptyset: '∅', forall: '∀', exists: '∃',
  sum: 'Σ', prod: 'Π', int: '∫', partial: '∂', nabla: '∇', prime: '′', ldots: '…', cdots: '⋯', dots: '…',
  alpha: 'α', beta: 'β', gamma: 'γ', delta: 'δ', epsilon: 'ε', varepsilon: 'ε', zeta: 'ζ', eta: 'η', theta: 'θ',
  iota: 'ι', kappa: 'κ', lambda: 'λ', mu: 'μ', nu: 'ν', xi: 'ξ', pi: 'π', rho: 'ρ', sigma: 'σ', tau: 'τ',
  upsilon: 'υ', phi: 'φ', varphi: 'φ', chi: 'χ', psi: 'ψ', omega: 'ω',
  Gamma: 'Γ', Delta: 'Δ', Theta: 'Θ', Lambda: 'Λ', Xi: 'Ξ', Pi: 'Π', Sigma: 'Σ', Phi: 'Φ', Psi: 'Ψ', Omega: 'Ω',
  sin: 'sin', cos: 'cos', tan: 'tan', cot: 'cot', sec: 'sec', csc: 'csc', log: 'log', ln: 'ln', lim: 'lim',
  max: 'max', min: 'min', exp: 'exp', det: 'det',
};

const GREEK = new Set([
  'alpha', 'beta', 'gamma', 'delta', 'epsilon', 'varepsilon', 'zeta', 'eta', 'theta', 'iota', 'kappa', 'lambda', 'mu', 'nu', 'xi', 'pi',
  'rho', 'sigma', 'tau', 'upsilon', 'phi', 'varphi', 'chi', 'psi', 'omega',
  'Gamma', 'Delta', 'Theta', 'Lambda', 'Xi', 'Pi', 'Sigma', 'Phi', 'Psi', 'Omega',
]);

/** Reads one {group} starting at the given index. Returns the inside text and the index after the closing brace. */
function group(s: string, start: number): [string, number] | null {
  if (s[start] !== '{') return null;
  let depth = 0;
  for (let i = start; i < s.length; i++) {
    if (s[i] === '{') depth++;
    else if (s[i] === '}' && --depth === 0) return [s.slice(start + 1, i), i + 1];
  }
  return null;
}

function isSimple(s: string): boolean {
  return /^[\p{L}\p{N}.]+$/u.test(s.trim());
}

/** Writes an exponent or subscript with Unicode when every character has one, otherwise as ^(...) or _(...). */
function script(text: string, table: Record<string, string>, fallbackMark: string): string {
  const t = text.trim();
  const mapped = [...t].map((c) => table[c]);
  if (t && mapped.every(Boolean)) return mapped.join('');
  return t.length === 1 ? fallbackMark + t : `${fallbackMark}(${t})`;
}

function convertMath(input: string): string {
  let s = input;

  // Layout commands that only add spacing or sizing.
  s = s.replace(/\\(left|right|big|Big|bigg|Bigg)(?![a-zA-Z])/g, '')
    .replace(/\\[,;:! ]/g, ' ')
    .replace(/\\quad|\\qquad/g, '  ');

  // Wrapping commands: keep their content.
  for (let guard = 0; guard < 20; guard++) {
    const m = /\\(text|textbf|textit|mathrm|mathbf|mathit|mathbb|operatorname|mbox|boldsymbol|overline|underline|vec|hat|bar)\s*\{/.exec(s);
    if (!m) break;
    const g = group(s, m.index + m[0].length - 1);
    if (!g) break;
    const inner = m[1] === 'vec' ? g[0] + '⃗' : m[1] === 'overline' || m[1] === 'bar' ? g[0] + '̄' : g[0];
    s = s.slice(0, m.index) + inner + s.slice(g[1]);
  }

  // Fractions and roots, innermost last so nesting works.
  for (let guard = 0; guard < 30; guard++) {
    const f = /\\[dt]?frac\s*\{/.exec(s);
    if (f) {
      const a = group(s, f.index + f[0].length - 1);
      const b = a && group(s, a[1]);
      if (a && b) {
        const top = convertMath(a[0]);
        const bottom = convertMath(b[0]);
        const wrap = (x: string) => (isSimple(x) ? x : `(${x})`);
        s = s.slice(0, f.index) + `${wrap(top)}/${wrap(bottom)}` + s.slice(b[1]);
        continue;
      }
    }
    const r = /\\sqrt\s*(\[([^\]]*)\])?\s*\{/.exec(s);
    if (r) {
      const g = group(s, r.index + r[0].length - 1);
      if (g) {
        const inner = convertMath(g[0]);
        const index = r[2] ? script(r[2], SUPER, '^') : '';
        s = s.slice(0, r.index) + `${index}√${isSimple(inner) ? inner : `(${inner})`}` + s.slice(g[1]);
        continue;
      }
    }
    break;
  }

  // A degree sign is written 30^\circ, but it is not a superscript in the output.
  s = s.replace(/\^\s*\{?\s*\\(circ|degree)\s*\}?/g, '°');

  // Symbols and Greek letters. LaTeX ignores the space after a command, so "\Delta T" reads "ΔT".
  s = s.replace(/\\([a-zA-Z]+)(\s+(?=[a-zA-Z0-9]))?/g, (whole, name: string, space?: string) => {
    if (!(name in SYMBOLS)) return name + (space ?? '');
    return SYMBOLS[name] + (GREEK.has(name) ? '' : space ?? '');
  });
  s = s.replace(/\\%/g, '%').replace(/\\\$/g, '$').replace(/\\&/g, '&').replace(/\\_/g, '_').replace(/\\#/g, '#').replace(/\\\\/g, ' ');

  // Exponents and subscripts, braced or single-character.
  s = s.replace(/\^\s*(\{[^{}]*\}|[^\s{}])/g, (_m, g: string) => script(g.startsWith('{') ? g.slice(1, -1) : g, SUPER, '^'));
  s = s.replace(/_\s*(\{[^{}]*\}|[^\s{}])/g, (_m, g: string) => script(g.startsWith('{') ? g.slice(1, -1) : g, SUB, '_'));

  return s.replace(/[{}]/g, '').replace(/\s{2,}/g, ' ').trim();
}

const MATH_SEGMENT = /\\\(([\s\S]+?)\\\)|\\\[([\s\S]+?)\\\]|\$\$([\s\S]+?)\$\$/g;

export function hasMath(text: string): boolean {
  MATH_SEGMENT.lastIndex = 0;
  return MATH_SEGMENT.test(text);
}

/** Replaces every maths segment in a piece of text with its readable form. Text outside maths is untouched. */
export function mathToText(text: string): string {
  return text.replace(MATH_SEGMENT, (_m, inline: string, display: string, dollars: string) => convertMath(inline ?? display ?? dollars));
}
