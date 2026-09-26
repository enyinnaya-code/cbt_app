// Writes the starter packs bundled inside the app to assets/starter/*.pack.
//
//   npm run starter                     builds the small original sample set (what ships by default)
//   node scripts/make-starter.mjs --from-api https://testacbt.com/api/v1 --token <token> jamb/english-language jamb/mathematics
//                                       downloads real packs from your server instead (needs an account token).
//                                       Check you have the rights to ship that content before you release the app.
//
// Starter questions get negative ids: they are never uploaded and can never clash with real questions.
import { mkdirSync, writeFileSync } from 'node:fs';
import { gunzipSync } from 'node:zlib';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { STARTER } from './starter-content.mjs';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const out = join(root, 'assets', 'starter');
mkdirSync(out, { recursive: true });

const args = process.argv.slice(2);
const flag = (name) => { const i = args.indexOf(name); return i > -1 ? args[i + 1] : null; };

async function fromApi(base, token, wanted) {
  for (const key of wanted) {
    const [exam, subject] = key.split('/');
    const res = await fetch(`${base.replace(/\/$/, '')}/packs/${exam}/${subject}`, { headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' } });
    if (!res.ok) throw new Error(`${key}: the server said ${res.status}`);
    const pack = JSON.parse(gunzipSync(Buffer.from(await res.arrayBuffer())).toString('utf8'));

    // Re-number to negative ids so bundled questions never sync.
    let n = 0;
    for (const paper of pack.papers) for (const item of paper.items) item.id = -(++n + Math.abs(paper.id) * 100000);
    pack.version = 0;
    writeFileSync(join(out, `${exam}-${subject}.pack`), JSON.stringify(pack));
    console.log(`wrote ${exam}-${subject}.pack (${n} items) from the server`);
  }
}

function sample() {
  let id = 0;

  for (const s of STARTER) {
    const items = s.items.map((it) => {
      id -= 1;
      if (it.passage) return { id, type: 'instruction', html: `<p>${it.passage}</p>` };

      const options = Object.fromEntries(it.options.map((text, i) => ['ABCDE'[i], text]));
      return {
        id, type: 'mcq', html: `<p>${it.text}</p>`, options, answer: it.answer, marks: 1, topic_id: null,
        explanation_en: `<p>${it.en}</p>`, explanation_pcm: `<p>${it.pcm}</p>`,
      };
    });

    const pack = {
      format: 1, version: 0, generated_at: new Date().toISOString(),
      exam: s.exam, subject: s.subject, topics: [],
      papers: [{ id: -1, year: 2025, title: `${s.subject.display_name} sample questions`, duration_minutes: null, items }],
    };

    writeFileSync(join(out, `${s.exam.slug}-${s.subject.slug}.pack`), JSON.stringify(pack));
    console.log(`wrote ${s.exam.slug}-${s.subject.slug}.pack (${items.filter((i) => i.type === 'mcq').length} questions)`);
  }
}

const base = flag('--from-api');
if (base) {
  const wanted = args.filter((a) => /^[a-z0-9-]+\/[a-z0-9-]+$/.test(a));
  if (!flag('--token') || !wanted.length) { console.error('Usage: --from-api <url> --token <token> exam/subject [exam/subject ...]'); process.exit(1); }
  await fromApi(base, flag('--token'), wanted);
} else {
  sample();
}
