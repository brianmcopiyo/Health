export const applySession = (payload, ability) => {
  if (payload.accessToken)
    useCookie('accessToken').value = payload.accessToken

  useCookie('userAbilityRules').value = payload.userAbilityRules
  ability.update(payload.userAbilityRules)
  useCookie('userData').value = payload.userData
}

export const clearSession = ability => {
  useCookie('accessToken').value = null
  useCookie('userData').value = null
  useCookie('userAbilityRules').value = null
  ability.update([])
}
