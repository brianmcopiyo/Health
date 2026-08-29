export const useProfilePhoto = () => {
  const userData = useCookie('userData')
  const photoUrl = ref(null)

  const refreshPhoto = async () => {
    if (photoUrl.value) {
      URL.revokeObjectURL(photoUrl.value)
      photoUrl.value = null
    }

    if (!userData.value?.hasAvatar)
      return

    try {
      const blob = await $api('/auth/avatar', { responseType: 'blob' })
      photoUrl.value = URL.createObjectURL(blob)
    }
    catch {
      photoUrl.value = null
    }
  }

  watch(() => `${userData.value?.id || ''}:${userData.value?.hasAvatar ? 1 : 0}`, refreshPhoto)
  onMounted(refreshPhoto)
  onBeforeUnmount(() => {
    if (photoUrl.value)
      URL.revokeObjectURL(photoUrl.value)
  })

  return { photoUrl, refreshPhoto }
}
