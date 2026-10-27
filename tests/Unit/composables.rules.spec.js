// @vitest-environment jsdom
/**
 * Правила валидации useForm.
 *
 * Контракт: правило возвращает `true` (ок) или строку (текст ошибки);
 * пустое значение невалидно только для required — остальные правила
 * пропускают пустое, чтобы не спорить с обязательностью.
 */
import { describe, it, expect } from 'vitest'
import {
  required,
  minLength,
  maxLength,
  length,
  pattern,
  email,
  numeric,
  integer,
  phone,
  min,
  max,
  oneOf,
  sameAs,
  unique,
  custom,
  isEmpty,
} from '../../resources/js/composables/validation/rules'

describe('rules: isEmpty', () => {
  it('пустыми считаются null/undefined/пробелы/пустой массив', () => {
    expect(isEmpty(null)).toBe(true)
    expect(isEmpty(undefined)).toBe(true)
    expect(isEmpty('   ')).toBe(true)
    expect(isEmpty([])).toBe(true)
    expect(isEmpty(0)).toBe(false)
    expect(isEmpty(false)).toBe(false)
    expect(isEmpty('x')).toBe(false)
  })
})

describe('rules: required', () => {
  it('возвращает текст для пустого и true для заполненного', () => {
    const rule = required('Нужно')
    expect(rule('')).toBe('Нужно')
    expect(rule('  ')).toBe('Нужно')
    expect(rule(null)).toBe('Нужно')
    expect(rule('ок')).toBe(true)
  })

  it('помечено свойством __required для звёздочки', () => {
    expect(required().__required).toBe(true)
    expect(minLength(3).__required).toBeUndefined()
  })
})

describe('rules: длина и шаблоны', () => {
  it('minLength/maxLength/length пропускают пустое', () => {
    expect(minLength(3)('')).toBe(true)
    expect(maxLength(3)('')).toBe(true)
    expect(length(2, 4)('')).toBe(true)
  })

  it('minLength/maxLength сообщают границы', () => {
    expect(minLength(3)('ab')).toContain('3')
    expect(minLength(3)('abc')).toBe(true)
    expect(maxLength(3)('abcd')).toContain('3')
    expect(length(2, 3)('a')).toContain('2')
    expect(length(2, 3)('abcd')).toContain('3')
  })

  it('email и pattern', () => {
    expect(email()('a@b.ru')).toBe(true)
    expect(email()('nope')).toContain('e-mail')
    expect(pattern(/^\d+$/, 'цифры')('12')).toBe(true)
    expect(pattern(/^\d+$/, 'цифры')('1a')).toBe('цифры')
  })

  it('numeric/integer/phone', () => {
    expect(numeric()('12.5')).toBe(true)
    expect(numeric()('1a')).toBeTruthy()
    expect(integer()('12')).toBe(true)
    expect(integer()('1.2')).toBeTruthy()
    expect(phone()('+7 (999) 123-45-67')).toBe(true)
    expect(phone()('123')).toBeTruthy()
  })

  it('min/max', () => {
    expect(min(5)('5')).toBe(true)
    expect(min(5)('4')).toContain('5')
    expect(max(5)('4')).toBe(true)
    expect(max(5)('6')).toContain('5')
  })
})

describe('rules: сравнения и списки', () => {
  it('oneOf', () => {
    expect(oneOf([1, 2])('1')).toBe(true)
    expect(oneOf([1, 2])(3)).toBeTruthy()
  })

  it('sameAs по имени поля и по геттеру', () => {
    expect(sameAs('password')('123', { password: '123' })).toBe(true)
    expect(sameAs('password')('x', { password: '123' })).toBeTruthy()
    expect(sameAs((v) => v.a)('1', { a: '1' })).toBe(true)
  })

  it('unique: находит дубль без учёта регистра и игнорирует себя', () => {
    const list = [{ name: 'Первая' }, { name: 'Вторая' }]
    expect(unique(list)('третья')).toBe(true)
    expect(unique(list)('ВТОРАЯ')).toBeTruthy()
    expect(unique(list, { ignore: 'Вторая' })('вторая')).toBe(true)
  })

  it('unique: принимает геттер списка', () => {
    const rule = unique(() => [{ name: 'A' }])
    expect(rule('A')).toBeTruthy()
    expect(rule('B')).toBe(true)
  })

  it('custom: строка или флаг', () => {
    expect(custom((v) => v > 0, 'мало')(5)).toBe(true)
    expect(custom((v) => v > 0, 'мало')(-1)).toBe('мало')
    expect(custom(() => 'явно')(1)).toBe('явно')
  })
})
