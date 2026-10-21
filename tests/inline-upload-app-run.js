'use strict';

// Simulated MCP App host: exercise the exact inline JavaScript produced by the
// PHP upload resource without a remote browser, network or production WordPress.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const php = fs.readFileSync(require('node:path').join(__dirname, '..', 'includes', 'class-wpvdmcp-mcp-apps.php'), 'utf8');
const match = php.match(/private static function upload_js\(\)\s*\{\s*return <<<'JS'\n([\s\S]*?)\nJS;/);
assert.ok(match, 'exact PHP upload_js nowdoc found');

async function runScenario(label, answers, expected) {
  const filePicker = { files: [{ name: 'test.png' }] };
  const button = { disabled: true };
  const status = { textContent: '' };
  const elements = { files: filePicker, upload: button, status };
  const created = [];
  const form = { style: {}, removed: false, submitted: 0, appendChild() {}, submit() { this.submitted++; }, remove() { this.removed = true; } };
  const frame = { style: {}, removed: false, remove() { this.removed = true; } };
  let checks = 0;
  const app = {
    connect: async () => undefined,
    callServerTool: async ({ name, arguments: args }) => {
      assert.equal(name, 'check_upload');
      assert.equal(args.upload_id, 'up_disposable');
      checks++;
      if (answers[checks - 1] === 'processing') {
        assert.equal(frame.removed, false, label + ': iframe must survive intermediate processing');
        assert.equal(form.removed, false, label + ': form must survive intermediate processing');
        assert.equal(button.disabled, true, label + ': concurrent submission remains blocked');
      }
      const state = answers[Math.min(checks - 1, answers.length - 1)];
      return { structuredContent: state === 'failed' ? { status: 'failed', orphaned_attachment_ids: [601] } : { status: state } };
    },
  };
  const document = {
    getElementById: id => elements[id],
    createElement: tag => tag === 'form' ? form : tag === 'iframe' ? frame : { style: {} },
    body: { append(...nodes) { created.push(...nodes); } },
  };
  class DataTransfer { constructor() { this.items = { add() {} }; this.files = filePicker.files; } }
  const context = { document, DataTransfer, createDirectMcpApp: () => app, setTimeout: fn => { queueMicrotask(fn); } };
  vm.runInNewContext(match[1], context, { filename: 'wpvdmcp-upload.js' });
  app.ontoolresult({ structuredContent: { upload_id: 'up_disposable', upload_url: 'https://example.test/upload' } });
  assert.equal(button.disabled, false, label + ': ticket enables input');
  await button.onclick();
  assert.equal(form.submitted, 1, label + ': submit once');
  assert.equal(created.length, 2, label + ': form and iframe were appended');
  assert.equal(frame.removed, expected.terminal, label + ': remove iframe only after confirmed terminal');
  assert.equal(form.removed, expected.terminal, label + ': remove form only after confirmed terminal');
  assert.equal(button.disabled, true, label + ': consumed or unresolved ticket cannot resubmit');
  assert.ok(status.textContent.includes(expected.message), label + ': relevant user-facing outcome');
  assert.equal(checks, expected.checks, label + ': expected server status checks');
  console.log('PASS ' + label);
}

(async () => {
  await runScenario('slow upload remains mounted through processing until ready', ['waiting', 'processing', 'processing', 'ready'], { terminal: true, checks: 4, message: 'Uploaded' });
  await runScenario('failed upload surfaces orphan attachment IDs', ['processing', 'failed'], { terminal: true, checks: 2, message: '601' });
  await runScenario('unresolved slow upload never tears down the iframe', ['processing'], { terminal: false, checks: 120, message: 'may still be running' });
})().catch(error => { console.error(error); process.exitCode = 1; });
