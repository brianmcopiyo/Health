import { ability } from '@/plugins/ability'
import { useCookie, writeStored } from '@/composables/useCookie'
import { catalog } from '@/navigation/modules'

const asRules = value => (Array.isArray(value) ? JSON.parse(JSON.stringify(value)) : [])

export const resolveHomeRoute = userData => {
  const names = new Set(catalog.map(module => module.to))
  const preferred = userData?.homeRoute

  if (preferred && preferred !== 'not-authorized' && names.has(preferred))
    return { name: preferred }

  const fromModule = catalog.find(module => (userData?.modules || []).includes(module.key))
  if (fromModule)
    return { name: fromModule.to }

  return { name: userData ? 'not-authorized' : 'login' }
}

export const applySession = (payload, currentAbility = ability) => {
  const token = payload.accessToken ?? useCookie('accessToken').value
  const rules = asRules(payload.userAbilityRules)
  const user = payload.userData ? JSON.parse(JSON.stringify(payload.userData)) : null

  writeStored('accessToken', token)
  writeStored('userAbilityRules', rules)
  writeStored('userData', user)

  useCookie('accessToken').value = token
  useCookie('userAbilityRules').value = rules
  useCookie('userData').value = user
  currentAbility.update(rules)
}

export const clearSession = (currentAbility = ability) => {
  writeStored('accessToken', null)
  writeStored('userData', null)
  writeStored('userAbilityRules', null)
  useCookie('accessToken').value = null
  useCookie('userData').value = null
  useCookie('userAbilityRules').value = null
  currentAbility.update([])
}
