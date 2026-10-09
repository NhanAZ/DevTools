import { appendFileSync, closeSync, lstatSync, openSync, readFileSync, readdirSync, realpathSync, writeFileSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { releaseFlags, verifyArtifact } from './artifact-contract.mjs';

const workflow = '.github/workflows/release-candidate.yml';
const shaPattern = /^[a-f0-9]{40}$/;
const digest = path => createHash('sha256').update(readFileSync(path)).digest('hex');
const readJson = path => JSON.parse(readFileSync(path, 'utf8'));

function requireValue(condition, message) {
  if (!condition) throw new Error(message);
}

function assetNames(version) {
  releaseFlags(version, 'tag', `v${version}`, '', 'auto');
  return [`DevTools-${version}.phar`, `DevTools-examples-${version}.zip`, 'RELEASE_NOTES.md'];
}

function checksumText(directory, names) {
  return names.map(name => `${digest(join(directory, name))}  ${name}\n`).join('');
}

export function createCandidate(directory, version, env = process.env) {
  requireValue(env.GITHUB_EVENT_NAME === 'workflow_dispatch', 'Candidates must be dispatched explicitly.');
  requireValue(shaPattern.test(env.GITHUB_SHA), 'Candidate requires the full GITHUB_SHA.');
  requireValue(/^\d+$/.test(env.GITHUB_RUN_ID) && /^\d+$/.test(env.GITHUB_RUN_ATTEMPT), 'Candidate requires a run ID and attempt.');
  requireValue(env.GITHUB_WORKFLOW_REF === `${env.GITHUB_REPOSITORY}/${workflow}@${env.GITHUB_REF}`, 'Candidate workflow identity is incorrect.');
  const names = assetNames(version);
  const assets = Object.fromEntries(names.map(name => {
    const path = join(directory, name);
    requireValue(lstatSync(path).isFile() && lstatSync(path).size > 0, `Candidate asset is not a nonempty regular file: ${name}`);
    return [name, digest(path)];
  }));
  const manifest = {
    schema_version: 1,
    repository: env.GITHUB_REPOSITORY,
    commit: env.GITHUB_SHA,
    workflow,
    run_id: env.GITHUB_RUN_ID,
    run_attempt: env.GITHUB_RUN_ATTEMPT,
    version,
    checks: { quality: 'passed', artifact: 'passed', runtime: 'not_run' },
    assets,
  };
  writeFileSync(join(directory, 'candidate.json'), `${JSON.stringify(manifest, null, 2)}\n`);
  writeFileSync(join(directory, 'SHA256SUMS.txt'), checksumText(directory, [...names, 'candidate.json']));
  return manifest;
}

export function validateIdentity(run, artifact, expected) {
  requireValue(run.repository?.full_name === expected.repository && run.head_repository?.full_name === expected.repository, 'Candidate run belongs to a different repository.');
  requireValue(String(run.id) === expected.run_id, 'Candidate run ID differs.');
  requireValue(run.path === workflow && run.workflow_id === expected.workflow_id, 'Candidate came from the wrong workflow.');
  requireValue(run.event === 'workflow_dispatch', 'Candidate must come from a manual workflow run.');
  requireValue(run.status === 'completed' && run.conclusion === 'success', 'Candidate run has not completed successfully.');
  requireValue(shaPattern.test(run.head_sha) && run.head_sha === expected.commit, 'Candidate commit does not match the existing release tag.');
  requireValue(Number.isSafeInteger(run.run_attempt) && run.run_attempt > 0, 'Candidate run attempt is invalid.');
  requireValue(String(artifact.id) === expected.artifact_id && artifact.workflow_run?.id === run.id, 'Artifact does not belong to the selected run.');
  requireValue(artifact.workflow_run?.head_sha === expected.commit, 'Artifact source commit differs from the tag.');
  requireValue(artifact.expired === false, 'Candidate artifact has expired.');
  requireValue(artifact.name === `DevTools-candidate-${expected.commit}-${expected.run_id}-${run.run_attempt}`, 'Artifact name does not match the candidate commit/run/attempt.');
  requireValue(/^sha256:[a-f0-9]{64}$/.test(artifact.digest), 'Candidate artifact has no immutable SHA-256 archive digest.');
  return { repository: expected.repository, commit: expected.commit, workflow, run_id: expected.run_id, run_attempt: String(run.run_attempt), artifact_id: expected.artifact_id, archive_digest: artifact.digest };
}

export function resolveCandidate(env = process.env, api = endpoint => JSON.parse(execFileSync('gh', ['api', endpoint], { encoding: 'utf8' }))) {
  const repository = env.GH_REPO;
  requireValue(/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/.test(repository), 'Invalid repository identity.');
  requireValue(/^[1-9]\d*$/.test(env.CANDIDATE_RUN_ID) && /^[1-9]\d*$/.test(env.CANDIDATE_ARTIFACT_ID), 'Candidate run and artifact IDs must be positive integers.');
  releaseFlags(env.RELEASE_TAG.replace(/^v/, ''), 'tag', env.RELEASE_TAG, '', 'auto');
  let target = api(`repos/${repository}/git/ref/tags/${encodeURIComponent(env.RELEASE_TAG)}`).object;
  for (let depth = 0; target?.type === 'tag' && depth < 5; depth++) {
    requireValue(shaPattern.test(target.sha), 'Invalid annotated tag object.');
    target = api(`repos/${repository}/git/tags/${target.sha}`).object;
  }
  requireValue(target?.type === 'commit' && shaPattern.test(target.sha), 'Release tag does not resolve to a commit.');
  const run = api(`repos/${repository}/actions/runs/${env.CANDIDATE_RUN_ID}`);
  const artifact = api(`repos/${repository}/actions/artifacts/${env.CANDIDATE_ARTIFACT_ID}`);
  const workflowInfo = api(`repos/${repository}/actions/workflows/release-candidate.yml`);
  return validateIdentity(run, artifact, { repository, commit: target.sha, run_id: env.CANDIDATE_RUN_ID, artifact_id: env.CANDIDATE_ARTIFACT_ID, workflow_id: workflowInfo.id });
}

export function verifyCandidate(directory, identity, tag) {
  const manifest = readJson(join(directory, 'candidate.json'));
  requireValue(manifest.schema_version === 1, 'Unsupported candidate manifest schema.');
  for (const key of ['repository', 'commit', 'workflow', 'run_id', 'run_attempt']) {
    requireValue(manifest[key] === identity[key], `Candidate provenance mismatch: ${key}`);
  }
  requireValue(manifest.checks?.quality === 'passed' && manifest.checks?.artifact === 'passed', 'Candidate lacks the required quality/artifact checks.');
  const flags = releaseFlags(manifest.version, 'tag', tag, identity.commit, 'auto');
  const names = assetNames(manifest.version);
  const expectedFiles = [...names, 'candidate.json', 'SHA256SUMS.txt'].sort();
  requireValue(JSON.stringify(readdirSync(directory).sort()) === JSON.stringify(expectedFiles), 'Candidate contains missing or unexpected files.');
  requireValue(JSON.stringify(Object.keys(manifest.assets ?? {}).sort()) === JSON.stringify([...names].sort()), 'Candidate asset list differs.');
  for (const name of expectedFiles) requireValue(lstatSync(join(directory, name)).isFile(), `Candidate file is not a regular file: ${name}`);
  for (const name of names) {
    requireValue(/^[a-f0-9]{64}$/.test(manifest.assets[name]), `Invalid asset SHA-256: ${name}`);
    verifyArtifact(join(directory, name), manifest.assets[name]);
  }
  requireValue(readFileSync(join(directory, 'SHA256SUMS.txt'), 'utf8') === checksumText(directory, [...names, 'candidate.json']), 'Candidate checksum manifest differs.');
  return { version: manifest.version, prerelease: String(flags.prerelease) };
}

export function verifyArchive(path, identity) {
  requireValue(/^sha256:[a-f0-9]{64}$/.test(identity.archive_digest), 'Missing GitHub artifact archive digest.');
  requireValue(`sha256:${digest(path)}` === identity.archive_digest, 'Downloaded archive SHA-256 differs from the immutable GitHub artifact digest.');
}

if (process.argv[1] && realpathSync(process.argv[1]) === realpathSync(fileURLToPath(import.meta.url))) {
  try {
    const [mode, path, extra] = process.argv.slice(2);
    if (mode === 'create') createCandidate(path, extra);
    else if (mode === 'resolve') writeFileSync(path, JSON.stringify(resolveCandidate()));
    else if (mode === 'download') {
      const identity = readJson(path);
      const descriptor = openSync(extra, 'wx');
      try {
        execFileSync('gh', ['api', `repos/${identity.repository}/actions/artifacts/${identity.artifact_id}/zip`], { stdio: ['ignore', descriptor, 'pipe'] });
      } finally {
        closeSync(descriptor);
      }
      verifyArchive(extra, identity);
    }
    else if (mode === 'verify') {
      const output = verifyCandidate(path, readJson(extra), process.env.RELEASE_TAG);
      appendFileSync(process.env.GITHUB_OUTPUT, Object.entries(output).map(([key, value]) => `${key}=${value}\n`).join(''));
    } else throw new Error('Expected create, resolve, download, or verify.');
  } catch (error) {
    console.error(error.message);
    process.exitCode = 1;
  }
}
