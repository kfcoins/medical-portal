const cart = [
  {
    medicine: { allow_pay_on_delivery: 0 }
  }
];

const hasDisabledPOD = cart.some(item => {
    const pod = item.medicine.allow_pay_on_delivery;
    return pod == 0 || pod === "0" || pod === false || pod === null || pod === undefined;
});

console.log("hasDisabledPOD is:", hasDisabledPOD);
