#!/usr/bin/env node
// Verifies frontend/src/locales/en.json and km.json stay in sync:
//   1. exact flattened-key parity (every key path in one exists in the other)
//   2. km.json never uses Khmer-script digits (០-៩) — plain 0-9 only
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import path from 'node:path'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const localesDir = path.join(__dirname, '..', 'src', 'locales')

function loadJson(file) {
  return JSON.parse(readFileSync(path.join(localesDir, file), 'utf8'))
}

function flatten(obj, prefix = '', out = {}) {
  for (const [key, value] of Object.entries(obj)) {
    const keyPath = prefix ? `${prefix}.${key}` : key
    if (value && typeof value === 'object' && !Array.isArray(value)) {
      flatten(value, keyPath, out)
    } else {
      out[keyPath] = value
    }
  }
  return out
}

const en = flatten(loadJson('en.json'))
const km = flatten(loadJson('km.json'))

const enKeys = new Set(Object.keys(en))
const kmKeys = new Set(Object.keys(km))

const missingInKm = [...enKeys].filter((k) => !kmKeys.has(k)).sort()
const missingInEn = [...kmKeys].filter((k) => !enKeys.has(k)).sort()

const khmerDigitRe = /[០-៩]/
const khmerDigitOffenders = Object.entries(km)
  .filter(([, value]) => typeof value === 'string' && khmerDigitRe.test(value))
  .map(([key]) => key)
  .sort()

let failed = false

if (missingInKm.length) {
  failed = true
  console.error(`Missing in km.json (${missingInKm.length}):`)
  missingInKm.forEach((k) => console.error(`  ${k}`))
}

if (missingInEn.length) {
  failed = true
  console.error(`Missing in en.json (${missingInEn.length}):`)
  missingInEn.forEach((k) => console.error(`  ${k}`))
}

if (khmerDigitOffenders.length) {
  failed = true
  console.error(`km.json uses Khmer-script digits, should be plain 0-9 (${khmerDigitOffenders.length}):`)
  khmerDigitOffenders.forEach((k) => console.error(`  ${k}: ${km[k]}`))
}

if (failed) {
  process.exit(1)
}

console.log(`OK — ${enKeys.size} keys, exact parity, no Khmer-script digits.`)
