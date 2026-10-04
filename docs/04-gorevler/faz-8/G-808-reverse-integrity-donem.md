# G-808 — Production reverse, integrity ve dönem devri

## Amaç

Production completion reverse zincirini, üretim/reçete integrity kontrollerini ve dönem devri davranışını tamamlamak.

## Önkoşul

G-802…G-807.

## Dokunulacak dosyalar

- ReverseProductionCompletion
- IntegrityProduction
- IntegrityRecipes
- period rollover production extensions
- reverse/integrity tests

## Şema / Kod

Reverse:

- finished product stock out
- component stock in
- production cost/moving-average ters etkileri
- reversal_of relation/history

Dönem:

- recipes/revisions taşınır
- open production order taşınmaz
- subcontractor location stock açılışı taşınır

## Kurallar

- Original completion immutable.
- Duplicate reverse yok.
- Reverse exact quantity/cost zinciri kullanır.
- Reçete ID ilişkileri dönem devrinde ürün ID sürekliliğiyle korunur.
- Açık order dönem kapanışında tamamlanmalı/iptal edilmeli.

## Kabul ölçütü

- Reverse net stock etkisini geri alıyor.
- Component fire dahil inverse doğru.
- Multi-output location inverse doğru.
- integrity:production consumption/output/cost farkını buluyor.
- integrity:recipes aktif/revision/snapshot farkını buluyor.
- Rollover recipe + subcontract location stock'u koruyor.
- Open production order taşınmıyor.

## İstem

> K-160…K-162 üretim reverse/integrity/dönem devri kurallarını tamamla.
