import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { createRequire } from 'node:module'

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..')
const { ref, unref } = createRequire(resolve(root, 'package.json'))('vue')

const source = readFileSync(resolve(root, 'resources/js/composables/usePageLoad.js'), 'utf8')
const start = source.indexOf('export const wrapSave =')
if (start < 0)
  throw new Error('wrapSave not found')

const end = source.indexOf('\nexport const ', start + 1)
const expr = source.slice(start, end < 0 ? undefined : end).replace(/^export const wrapSave = /, '').trim()

const saveError = error => {
  const data = error?.data
  const first = data?.errors ? Object.values(data.errors).flat().find(Boolean) : null
  return first || data?.message || error?.message || 'Unable to save'
}

const httpStatus = error => Number(error?.status || error?.response?.status || 0)
const toast = { success() {}, error() {} }
const notifyError = (formError, error) => {
  const message = saveError(error)
  if (formError)
    formError.value = message
  return message
}

const wrapSave = new Function(
  'unref',
  'httpStatus',
  'saveError',
  'toast',
  'notifyError',
  `return ${expr}`,
)(unref, httpStatus, saveError, toast, notifyError)

const assert = (ok, message) => {
  if (!ok)
    throw new Error(message)
}

const delay = ms => new Promise(resolve => setTimeout(resolve, ms))

const saving = ref(false)
const formError = ref('')
const kept = { name: 'Ada' }
let runs = 0

const slow = wrapSave(saving, formError, async () => {
  runs += 1
  await delay(40)
  assert(saving.value === true, 'loading should stay active during a slow request')
})

const duplicate = wrapSave(saving, formError, async () => {
  runs += 1
})

const [slowResult, duplicateResult] = await Promise.all([slow, duplicate])
assert(slowResult === true, 'slow request should succeed')
assert(duplicateResult === false, 'duplicate submit should be ignored')
assert(runs === 1, `expected one request, got ${runs}`)
assert(saving.value === false, 'loading should stop after success')
assert(formError.value === '', 'success should not leave an error')
assert(kept.name === 'Ada', 'entered data should remain after success')

const invalid = await wrapSave(saving, formError, async () => {
  throw { status: 422, data: { message: 'Unable to save', errors: { name: ['The name field is required.'] } } }
})
assert(invalid === false, 'validation failure should return false')
assert(saving.value === false, 'loading should stop after validation failure')
assert(formError.value === 'The name field is required.', `unexpected validation message: ${formError.value}`)
assert(kept.name === 'Ada', 'entered data should remain after validation failure')

const server = await wrapSave(saving, formError, async () => {
  throw { status: 500, data: { message: 'Unable to complete this request' } }
})
assert(server === false, 'server error should return false')
assert(saving.value === false, 'loading should stop after server error')
assert(formError.value === 'Unable to complete this request', `unexpected server message: ${formError.value}`)
assert(kept.name === 'Ada', 'entered data should remain after server error')

console.log('wrapSave ok')
