import { createMongoAbility } from '@casl/ability'
import { useCookie } from '@/composables/useCookie'

export const ability = createMongoAbility([])

export const hydrateAbility = () => {
  const rules = useCookie('userAbilityRules').value
  ability.update(Array.isArray(rules) ? rules : [])
}
