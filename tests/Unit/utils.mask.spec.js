// @vitest-environment jsdom
/**
 * Маски ввода: форматирование, цифры, снятие маски.
 */
import { describe, it, expect } from 'vitest'
import { applyMask, digitsOnly, unmask, MASK_PRESETS } from '../../resources/js/utils/mask'

describe('mask: applyMask', () => {
  it('телефон из «голых» цифр', () => {
    expect(applyMask('9991234567', MASK_PRESETS.phone)).toBe('+7 (999) 123-45-67')
  })

  it('телефон с уже введённым префиксом +7', () => {
    expect(applyMask('+79991234567', MASK_PRESETS.phone)).toBe('+7 (999) 123-45-67')
  })

  it('идемпотентна к отформатированному значению', () => {
    const once = applyMask('9991234567', MASK_PRESETS.phone)
    expect(applyMask(once, MASK_PRESETS.phone)).toBe(once)
  })

  it('дата DD.MM.YYYY', () => {
    expect(applyMask('31122024', MASK_PRESETS.date)).toBe('31.12.2024')
    expect(applyMask('31.12.2024', MASK_PRESETS.date)).toBe('31.12.2024')
  })

  it('частичный ввод не дописывает хвостовые литералы', () => {
    expect(applyMask('99', MASK_PRESETS.phone)).toBe('+7 (99')
    expect(applyMask('9', MASK_PRESETS.inn)).toBe('9')
  })

  it('нечисловые символы на месте цифры пропускаются', () => {
    expect(applyMask('9a9b9', MASK_PRESETS.inn)).toBe('999')
  })

  it('пустое значение', () => {
    expect(applyMask('', MASK_PRESETS.phone)).toBe('')
    expect(applyMask(null, MASK_PRESETS.phone)).toBe('')
  })
})

describe('mask: digitsOnly', () => {
  it('оставляет цифры и режет по лимиту', () => {
    expect(digitsOnly('+7 (999) abc 12')).toBe('799912')
    expect(digitsOnly('123456', 4)).toBe('1234')
  })
})

describe('mask: unmask', () => {
  it('снимает маску даты', () => {
    expect(unmask('31.12.2024', MASK_PRESETS.date)).toBe('31122024')
  })

  it('фиксированный литерал шаблона (код +7) в значимые не входит', () => {
    // Для полного номера с кодом страны есть digitsOnly().
    expect(unmask('+7 (999) 123-45-67', MASK_PRESETS.phone)).toBe('9991234567')
    expect(digitsOnly('+7 (999) 123-45-67')).toBe('79991234567')
  })

  it('без шаблона убирает всё, кроме букв и цифр', () => {
    expect(unmask('Иванов И. И.')).toBe('ИвановИИ')
  })
})
