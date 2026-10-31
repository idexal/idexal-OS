import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { buildReport, limitSkips, securityFindings } from './warden-report.mjs';
import { decide, renderComment } from './warden-clearance.mjs';

// Runs in the existing Warden policy/report test job, without model calls.
test('chunk cost tuning keeps both skills, exclusions and scan coverage unchanged', () => {
  const policy = readFileSync(new URL('../../warden.toml', import.meta.url), 'utf8');
  const section = (name) => policy.match(new RegExp(`^\\[${name.replaceAll('.', '\\.')}\\]\\n([\\s\\S]*?)(?=^\\[|$(?![\\s\\S]))`, 'm'))?.[1]
    .split('\n').filter((line) => line.trim() && !line.startsWith('#')).join('\n');
  assert.equal(section('defaults'), 'defaultBranch = "dev"\nreportOn = "low"\nignorePaths = ["**/node_modules/**", "**/pnpm-lock.yaml", "**/dist/**", "**/*.min.js"]');
  assert.equal(section('defaults.scan'), 'maxFileLines = 5000\nmaxFiles = 400\nmaxChangedLines = 60000');
  assert.equal(section('defaults.chunking.coalesce'), 'enabled = true\nmaxGapLines = 30\nmaxChunkSize = 12000');
  assert.deepEqual([...policy.matchAll(/^name = "([^"]+)"$/gm)].map((match) => match[1]), ['diff-security-review', 'confidentiality-review']);
  // Reject skill-scoped filters and global skips as well as new hidden tables.
  assert.equal([...policy.matchAll(/^ignorePaths =/gm)].length, 1);
  assert.doesNotMatch(policy, /^(paths|filePatterns|pattern|mode)\s*=|^\[defaults\.ignore\]/m);
  assert.deepEqual([...policy.matchAll(/^\[([^\[\]]+)\]$/gm)].map((match) => match[1]), ['defaults', 'defaults.scan', 'defaults.chunking.coalesce', 'defaults.agent', 'defaults.auxiliary']);
  assert.equal([...policy.matchAll(/^type = "pull_request"$/gm)].length, 2);
  assert.equal([...policy.matchAll(/^type = "local"$/gm)].length, 2);
  assert.equal([...policy.matchAll(/^failOn = "info"$/gm)].length, 2);
  assert.equal([...policy.matchAll(/^minConfidence = "low"$/gm)].length, 2);
  assert.equal(section('defaults.agent'), 'model = "openai/gpt-6-luna"');
  assert.equal(section('defaults.auxiliary'), 'model = "openai/gpt-6-luna"');
});

const meta = { repository: 'sample-org/sample-project', pr: 7, head: 'a'.repeat(40), base: 'b'.repeat(40), runId: '99', attempt: 1, outcome: 'success', started: 0, analysisStarted: 0 };
const finding = (severity, title) => ({ id: title, severity, title, description: `${title} details`, location: { path: 'src/auth.ts', startLine: 3 } });

function raw({ security = [], skipped = [], failedHunks = 0 } = {}) {
  const skills = [
    { name: 'diff-security-review', findings: security, durationMs: 1, failedHunks, skippedFiles: skipped },
    { name: 'confidentiality-review', findings: [], durationMs: 1, skippedFiles: skipped },
  ];
  return {
    version: '1', event: 'pull_request', runId: '99',
    repository: { fullName: meta.repository }, pullRequest: { number: 7, headSha: meta.head },
    skills,
    triggerResults: skills.map((skill) => ({ skillName: skill.name, status: 'success', report: { skill: skill.name, findings: skill.findings } })),
    summary: { totalSkills: 2, totalFindings: security.length, findingsBySeverity: { high: security.filter((f) => f.severity === 'high').length, medium: 0, low: security.filter((f) => f.severity === 'low').length } },
  };
}

const overLimit = [
  { filename: 'src/a.ts', reason: 'limit:changed_lines' },
  { filename: 'src/b.ts', reason: 'limit:changed_lines' },
  { filename: 'src/c.ts', reason: 'limit:file_count' },
  { filename: 'pnpm-lock.yaml', reason: 'ignored:user' },
];

test('only size-limit skips count, once per file', () => {
  assert.deepEqual(limitSkips(raw({ skipped: overLimit })), { count: 3, reasons: { 'limit:changed_lines': 2, 'limit:file_count': 1 } });
  assert.deepEqual(limitSkips(raw({ skipped: [{ filename: 'pnpm-lock.yaml', reason: 'ignored:user' }] })), { count: 0, reasons: {} });
});

test('files skipped over the size limits make the review incomplete', () => {
  const report = buildReport(raw({ skipped: overLimit }), meta);
  assert.equal(report.review_complete, false);
  assert.ok(report.incomplete_reasons.includes('files-over-size-limits'));
  assert.equal(buildReport(raw(), meta).review_complete, true);
});

test('findings from a partial review are kept, marked incomplete, with the skips', () => {
  const data = raw({ security: [finding('high', 'Role check bypass'), finding('low', 'Note')], skipped: overLimit });
  const report = buildReport(data, meta);
  const findings = securityFindings(report, data);
  assert.equal(findings.complete, false);
  assert.equal(findings.total, 2);
  assert.equal(findings.findings[0].title, 'Role check bypass');
  assert.equal(findings.skipped.count, 3);
});

test('findings from a different run are never published', () => {
  const data = raw({ security: [finding('high', 'x')] });
  assert.equal(securityFindings(buildReport(data, { ...meta, runId: '100' }), data), null);
});

test('clearance names an oversized PR, but only from its own receipt, and never approves it', () => {
  const data = raw({ security: [finding('high', 'Role check bypass')], skipped: overLimit });
  const receipt = buildReport(data, meta);
  const expected = { conclusion: 'failure', repository: meta.repository, runId: '99', attempt: 1, head: meta.head, pr: 7 };
  assert.deepEqual(decide(receipt, expected), { verdict: 'flagged', reason: 'files-over-size-limits' });
  assert.equal(decide(receipt, { ...expected, runId: '100' }).reason, 'analysis-failure');
  assert.equal(decide(buildReport(raw(), meta), { ...expected, conclusion: 'success' }).verdict, 'clear');
});

test('the PR comment explains the size limit and shows findings so far', () => {
  const data = raw({ security: [finding('high', 'Role check bypass')], skipped: overLimit });
  const receipt = buildReport(data, meta);
  const decision = decide(receipt, { conclusion: 'failure', repository: meta.repository, runId: '99', attempt: 1, head: meta.head, pr: 7 });
  const body = renderComment(decision, securityFindings(receipt, data), [], { HEAD_SHA: meta.head, RUN_URL: 'https://example.test/run' });
  assert.match(body, /too big for Warden to review in full/);
  assert.match(body, /skipped 3 files/);
  assert.match(body, /Security findings so far/);
  assert.match(body, /Role check bypass/);
});
