namespace Mars.Application.Foundation.Configuration;

public static class StartupConfigurationValidation
{
    public static void ThrowIfInvalid<TOptions>(
        TOptions options,
        IEnumerable<IStartupConfigurationValidator<TOptions>> validators)
    {
        ArgumentNullException.ThrowIfNull(options);
        ArgumentNullException.ThrowIfNull(validators);

        var issues = validators
            .SelectMany(validator => validator.Validate(options)
                ?? throw new InvalidOperationException(
                    $"{validator.GetType().Name} returned a null validation result."))
            .ToArray();

        if (issues.Length > 0)
        {
            throw new StartupConfigurationException(issues);
        }
    }
}
